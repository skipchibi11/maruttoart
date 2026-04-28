/* global MaruttoArt, wp */
(function () {
  'use strict';

  var el          = wp.element.createElement;
  var useState    = wp.element.useState;
  var useEffect   = wp.element.useEffect;
  var useRef      = wp.element.useRef;
  var Fragment    = wp.element.Fragment;

  var registerPlugin   = wp.plugins.registerPlugin;
  var PluginSidebar    = wp.editPost.PluginSidebar;
  var TextControl      = wp.components.TextControl;
  var Button           = wp.components.Button;
  var Spinner          = wp.components.Spinner;
  var Notice           = wp.components.Notice;

  var insertBlocks     = wp.data.dispatch('core/block-editor').insertBlocks;
  var createBlock      = wp.blocks.createBlock;

  var API = MaruttoArt.apiBase;

  // ---- 画像グリッドコンポーネント ----
  function Grid(props) {
    var items    = props.items;
    var selected = props.selected;
    var onSelect = props.onSelect;

    return el('div', { className: 'marutto-grid' },
      items.map(function (item) {
        var imgSrc = (item.images && (item.images.webp_small || item.images.original)) || '';
        if (!imgSrc) return null;
        return el('div', {
          key:       item.id,
          className: 'marutto-item' + (selected && selected.id === item.id ? ' is-selected' : ''),
          tabIndex:  0,
          title:     item.title,
          onClick:   function () { onSelect(item); },
          onKeyDown: function (e) { if (e.key === 'Enter' || e.key === ' ') onSelect(item); },
        },
          el('img', { src: imgSrc, alt: item.title, loading: 'lazy' }),
          el('div', { className: 'marutto-item-title' }, item.title)
        );
      })
    );
  }

  // ---- ページネーションコンポーネント ----
  function Pager(props) {
    var pg = props.pagination;
    if (!pg || pg.last_page <= 1) return null;
    return el('div', { className: 'marutto-pagination' },
      pg.prev_page_url
        ? el(Button, { variant: 'secondary', isSmall: true, onClick: function () { props.onPage(pg.current_page - 1); } }, '« 前へ')
        : null,
      el('span', { style: { fontSize: '12px', lineHeight: '28px' } },
        pg.current_page + ' / ' + pg.last_page),
      pg.next_page_url
        ? el(Button, { variant: 'secondary', isSmall: true, onClick: function () { props.onPage(pg.current_page + 1); } }, '次へ »')
        : null
    );
  }

  // ---- メインパネル ----
  function MaruttoPanel() {
    var _query      = useState('');
    var query       = _query[0]; var setQuery    = _query[1];
    var _input      = useState('');
    var inputVal    = _input[0]; var setInputVal = _input[1];
    var _items      = useState([]);
    var items       = _items[0]; var setItems    = _items[1];
    var _pg         = useState(null);
    var pagination  = _pg[0];   var setPg       = _pg[1];
    var _page       = useState(1);
    var page        = _page[0]; var setPage     = _page[1];
    var _loading    = useState(false);
    var loading     = _loading[0]; var setLoading = _loading[1];
    var _error      = useState('');
    var error       = _error[0]; var setError   = _error[1];
    var _selected   = useState(null);
    var selected    = _selected[0]; var setSelected = _selected[1];

    var mounted = useRef(true);
    useEffect(function () {
      return function () { mounted.current = false; };
    }, []);

    // 初回ロード
    useEffect(function () {
      fetchData(query, page);
    }, [query, page]);

    function fetchData(q, p) {
      setLoading(true);
      setError('');
      setItems([]);
      setPg(null);

      var url = q
        ? API + '/search?q=' + encodeURIComponent(q) + '&page=' + p + '&per_page=24'
        : API + '/materials?page=' + p + '&per_page=24';

      fetch(url)
        .then(function (r) { return r.json(); })
        .then(function (json) {
          if (!mounted.current) return;
          setItems(json.data || []);
          setPg(json.pagination || null);
        })
        .catch(function () {
          if (!mounted.current) return;
          setError('読み込みに失敗しました。');
        })
        .finally(function () {
          if (!mounted.current) return;
          setLoading(false);
        });
    }

    function onSearch() {
      var q = inputVal.trim();
      setQuery(q);
      setPage(1);
    }

    function onPage(p) {
      setPage(p);
      setSelected(null);
    }

    function onInsert() {
      if (!selected) return;
      var imgSrc = selected.images.original || selected.images.webp_medium || selected.images.webp_small || '';
      insertBlocks(
        createBlock('core/image', {
          url: imgSrc,
          alt: selected.title,
          caption: '出典: <a href="' + (selected.detail_url || 'https://marutto.art/') + '">marutto.art</a>',
        })
      );
      setSelected(null);
    }

    return el('div', { className: 'marutto-sidebar' },

      // 検索バー
      el('div', { className: 'marutto-search-bar' },
        el(TextControl, {
          value:       inputVal,
          onChange:    setInputVal,
          placeholder: 'キーワードで検索...',
          onKeyDown:   function (e) { if (e.key === 'Enter') onSearch(); },
          hideLabelFromVision: true,
          label: '検索',
        }),
        el(Button, { variant: 'secondary', isSmall: true, onClick: onSearch }, '検索')
      ),

      // ローディング
      loading && el('div', { className: 'marutto-status' }, el(Spinner)),

      // エラー
      error && el(Notice, { status: 'error', isDismissible: false }, error),

      // グリッド
      !loading && !error && items.length === 0
        ? el('div', { className: 'marutto-status' }, '素材が見つかりません。')
        : el(Grid, { items: items, selected: selected, onSelect: setSelected }),

      // ページネーション
      el(Pager, { pagination: pagination, onPage: onPage }),

      // 挿入ボタン
      el(Button, {
        variant:   'primary',
        disabled:  !selected,
        onClick:   onInsert,
        style:     { marginTop: '10px', width: '100%', justifyContent: 'center' },
      }, selected ? '「' + selected.title + '」を挿入' : '素材を選択してください')
    );
  }

  // ---- プラグイン登録 ----
  registerPlugin('marutto-art', {
    render: function () {
      return el(Fragment, {},
        el(PluginSidebar, {
          name:  'marutto-art-sidebar',
          title: 'marutto.art 素材挿入',
          icon:  'format-image',
        }, el(MaruttoPanel))
      );
    },
  });

}());
