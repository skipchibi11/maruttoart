/* global MaruttoArt, send_to_editor, jQuery */
(function ($) {
  'use strict';

  var api     = MaruttoArt.apiBase;
  var i18n    = MaruttoArt.i18n;
  var selected = null;
  var state   = { page: 1, query: '', loading: false };

  // ---- ダイアログ HTML を body に追加 ----
  var $dialog = $('<div id="marutto-dialog"></div>').appendTo('body');
  var $inner  = $('<div class="marutto-inner"></div>').appendTo($dialog);

  var $searchBar = $(
    '<div class="marutto-search-bar">' +
      '<input type="text" class="regular-text" placeholder="キーワードで検索..." id="marutto-q">' +
      '<button type="button" class="button button-secondary" id="marutto-search-btn">' + i18n.search + '</button>' +
    '</div>'
  ).appendTo($inner);

  var $grid   = $('<div class="marutto-grid"></div>').appendTo($inner);
  var $status = $('<div class="marutto-status"></div>').appendTo($inner);
  var $pager  = $('<div class="marutto-pagination"></div>').appendTo($inner);

  // ---- jQuery UI Dialog 初期化 ----
  $dialog.dialog({
    title:     i18n.dialogTitle,
    modal:     true,
    autoOpen:  false,
    width:     700,
    height:    560,
    resizable: false,
    buttons: [
      {
        text:  i18n.insert,
        class: 'button button-primary',
        click: function () {
          if (!selected) return;
          var html = '<img src="' + selected.src + '" alt="' + escAttr(selected.title) + '">';
          send_to_editor(html);
          $dialog.dialog('close');
          selected = null;
        }
      }
    ]
  });

  // ---- 開くボタン ----
  $(document).on('click', '#marutto-art-open-btn', function () {
    selected = null;
    state.page  = 1;
    state.query = '';
    $('#marutto-q').val('');
    $dialog.dialog('open');
    fetchMaterials();
  });

  // ---- 検索 ----
  $(document).on('click', '#marutto-search-btn', function () {
    state.query = $.trim($('#marutto-q').val());
    state.page  = 1;
    fetchMaterials();
  });
  $(document).on('keydown', '#marutto-q', function (e) {
    if (e.key === 'Enter') { $('#marutto-search-btn').trigger('click'); }
  });

  // ---- API 呼び出し ----
  function fetchMaterials() {
    if (state.loading) return;
    state.loading = true;

    $grid.empty();
    $pager.empty();
    $status.text(i18n.loading);

    var endpoint = state.query
      ? api + '/search?q=' + encodeURIComponent(state.query) + '&page=' + state.page + '&per_page=30'
      : api + '/materials?page=' + state.page + '&per_page=30';

    $.getJSON(endpoint)
      .done(function (json) {
        var items = json.data || [];
        $status.text('');

        if (!items.length) {
          $status.text(i18n.noResults);
          return;
        }

        $.each(items, function (_, item) {
          var imgSrc = item.images.webp_small || item.images.original || '';
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
            selected = {
              src:   item.images.original || imgSrc,
              title: item.title
            };
          });

          $grid.append($item);
        });

        // ページネーション
        var pg = json.pagination;
        if (pg && pg.last_page > 1) {
          if (pg.prev_page_url) {
            $('<button class="button">« 前へ</button>').on('click', function () {
              state.page--;
              fetchMaterials();
            }).appendTo($pager);
          }
          $('<span style="line-height:28px;font-size:12px">' + pg.current_page + ' / ' + pg.last_page + '</span>').appendTo($pager);
          if (pg.next_page_url) {
            $('<button class="button">次へ »</button>').on('click', function () {
              state.page++;
              fetchMaterials();
            }).appendTo($pager);
          }
        }
      })
      .fail(function () {
        $status.text(i18n.error);
      })
      .always(function () {
        state.loading = false;
      });
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
