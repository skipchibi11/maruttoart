/* global MaruttoArt, send_to_editor, jQuery */
(function ($) {
  'use strict';

  var api      = MaruttoArt.apiBase;
  var i18n     = MaruttoArt.i18n;
  var restUrl  = MaruttoArt.restUrl;
  var restNonce = MaruttoArt.restNonce;

  var selected  = null;
  var uploading = false;
  var state     = { tab: 'materials', page: 1, query: '', month: new Date().getMonth() + 1, loading: false };

  // ---- ダイアログ HTML ----
  var $dialog = $('<div id="marutto-dialog"></div>').appendTo('body');
  var $inner  = $('<div class="marutto-inner"></div>').appendTo($dialog);

  // タブ
  var $tabs = $('<div class="marutto-tabs"></div>').appendTo($inner);
  var TABS  = [
    { key: 'materials', label: i18n.tabMaterials },
    { key: 'community', label: i18n.tabCommunity },
    { key: 'calendar',  label: i18n.tabCalendar  },
  ];
  $.each(TABS, function (_, t) {
    $('<button type="button" class="marutto-tab-btn' + (t.key === state.tab ? ' is-active' : '') + '" data-tab="' + t.key + '">' + escHtml(t.label) + '</button>')
      .appendTo($tabs);
  });

  // 検索バー
  var $searchBar = $(
    '<div class="marutto-search-bar">' +
      '<input type="text" class="regular-text" placeholder="キーワードで検索..." id="marutto-q">' +
      '<button type="button" class="button button-secondary" id="marutto-search-btn">' + i18n.search + '</button>' +
    '</div>'
  ).appendTo($inner);

  // 月セレクター（カレンダー用）
  var monthOptions = '';
  for (var m = 1; m <= 12; m++) {
    monthOptions += '<option value="' + m + '"' + (m === state.month ? ' selected' : '') + '>' + m + '月</option>';
  }
  var $monthSelect = $(
    '<div class="marutto-month-select" style="display:none">' +
      '<label for="marutto-month">月を選択:</label>' +
      '<select id="marutto-month">' + monthOptions + '</select>' +
    '</div>'
  ).appendTo($inner);

  var $grid   = $('<div class="marutto-grid"></div>').appendTo($inner);
  var $status = $('<div class="marutto-status"></div>').appendTo($inner);
  var $pager  = $('<div class="marutto-pagination"></div>').appendTo($inner);

  // ---- jQuery UI Dialog ----
  $dialog.dialog({
    title:     i18n.dialogTitle,
    modal:     true,
    autoOpen:  false,
    width:     700,
    height:    580,
    resizable: false,
    buttons: [
      {
        id:    'marutto-insert-btn',
        text:  i18n.insert,
        class: 'button button-primary',
        click: doInsert,
      }
    ]
  });

  // ---- 挿入処理（メディアライブラリへアップロード） ----
  function doInsert() {
    if (!selected || uploading) return;

    var item     = selected;
    var src      = (item.images && (item.images.original || item.images.webp_small)) || '';
    var alt      = item.title;
    var filename = src.split('/').pop().split('?')[0] || 'image.png';

    if (!src) {
      $dialog.dialog('close');
      selected = null;
      return;
    }

    // ---- アップロード開始 ----
    uploading = true;
    $status.text(i18n.inserting);
    $('#marutto-insert-btn').prop('disabled', true).text(i18n.inserting);

    fetch(src)
      .then(function (r) {
        if (!r.ok) throw new Error('fetch');
        return r.blob();
      })
      .then(function (blob) {
        return fetch(restUrl, {
          method:      'POST',
          credentials: 'include',
          headers: {
            'Content-Type':        blob.type || 'image/png',
            'Content-Disposition': 'attachment; filename="' + filename + '"',
            'X-WP-Nonce':          restNonce,
          },
          body: blob,
        }).then(function (r) {
          if (!r.ok) throw new Error('upload ' + r.status);
          return r.json();
        });
      })
      .then(function (media) {
        var url = media.source_url || src;
        send_to_editor('<img src="' + escAttr(url) + '" alt="' + escAttr(alt) + '">');
        $dialog.dialog('close');
      })
      .catch(function () {
        // フォールバック: URL参照で挿入
        send_to_editor('<img src="' + escAttr(src) + '" alt="' + escAttr(alt) + '">');
        $dialog.dialog('close');
      })
      .finally(function () {
        uploading = false;
        selected  = null;
        $status.text('');
        $('#marutto-insert-btn').prop('disabled', false).text(i18n.insert);
      });
  }

  // ---- タブ切り替え ----
  $(document).on('click', '.marutto-tab-btn', function () {
    var tab = $(this).data('tab');
    if (tab === state.tab) return;
    state.tab   = tab;
    state.page  = 1;
    state.query = '';
    selected    = null;
    $('#marutto-q').val('');
    $('.marutto-tab-btn').removeClass('is-active');
    $(this).addClass('is-active');
    if (tab === 'calendar') { $searchBar.hide(); $monthSelect.show(); }
    else                    { $searchBar.show(); $monthSelect.hide(); }
    fetchItems();
  });

  // ---- 開くボタン ----
  $(document).on('click', '#marutto-art-open-btn', function () {
    selected    = null;
    state.page  = 1;
    state.query = '';
    state.tab   = 'materials';
    $('#marutto-q').val('');
    $('.marutto-tab-btn').removeClass('is-active').filter('[data-tab="materials"]').addClass('is-active');
    $searchBar.show();
    $monthSelect.hide();
    $dialog.dialog('open');
    fetchItems();
  });

  // ---- 検索 ----
  $(document).on('click', '#marutto-search-btn', function () {
    state.query = $.trim($('#marutto-q').val());
    state.page  = 1;
    fetchItems();
  });
  $(document).on('keydown', '#marutto-q', function (e) {
    if (e.key === 'Enter') { $('#marutto-search-btn').trigger('click'); }
  });

  // ---- 月選択 ----
  $(document).on('change', '#marutto-month', function () {
    state.month = parseInt($(this).val(), 10);
    state.page  = 1;
    fetchItems();
  });

  // ---- API 呼び出し ----
  function buildUrl() {
    if (state.tab === 'community') {
      var u = api + '/community-artworks?page=' + state.page + '&per_page=30';
      if (state.query) u += '&q=' + encodeURIComponent(state.query);
      return u;
    }
    if (state.tab === 'calendar') {
      return api + '/everyone-calendars?month=' + state.month + '&page=' + state.page + '&per_page=30';
    }
    return state.query
      ? api + '/search?q=' + encodeURIComponent(state.query) + '&page=' + state.page + '&per_page=30'
      : api + '/materials?page=' + state.page + '&per_page=30';
  }

  function fetchItems() {
    if (state.loading) return;
    state.loading = true;
    selected = null;
    $grid.empty();
    $pager.empty();
    $status.text(i18n.loading);

    $.getJSON(buildUrl())
      .done(function (json) {
        var items = json.data || [];
        $status.text('');
        if (!items.length) { $status.text(i18n.noResults); return; }

        $.each(items, function (_, item) {
          var imgSrc = (item.images && (item.images.webp_small || item.images.original)) || '';
          if (!imgSrc) return;

          var $item = $(
            '<div class="marutto-item" tabindex="0">' +
              '<img src="' + escAttr(imgSrc) + '" loading="lazy" alt="' + escAttr(item.title) + '">' +
              '<div class="marutto-item-title">' + escHtml(item.title) + '</div>' +
            '</div>'
          ).data('item', item);

          $item.on('click keydown', function (e) {
            if (e.type === 'keydown' && e.key !== 'Enter' && e.key !== ' ') return;
            $('.marutto-item').removeClass('is-selected');
            $(this).addClass('is-selected');
            selected = item;
          });

          $grid.append($item);
        });

        var pg = json.pagination;
        if (pg && pg.last_page > 1) {
          if (pg.prev_page_url) {
            $('<button class="button">« 前へ</button>').on('click', function () { state.page--; fetchItems(); }).appendTo($pager);
          }
          $('<span style="line-height:28px;font-size:12px">' + pg.current_page + ' / ' + pg.last_page + '</span>').appendTo($pager);
          if (pg.next_page_url) {
            $('<button class="button">次へ »</button>').on('click', function () { state.page++; fetchItems(); }).appendTo($pager);
          }
        }
      })
      .fail(function () { $status.text(i18n.error); })
      .always(function () { state.loading = false; });
  }

  function escAttr(str) {
    return String(str).replace(/[&"<>]/g, function (c) {
      return ({ '&': '&amp;', '"': '&quot;', '<': '&lt;', '>': '&gt;' })[c];
    });
  }
  function escHtml(str) {
    return String(str).replace(/[&<>]/g, function (c) {
      return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;' })[c];
    });
  }

}(jQuery));
