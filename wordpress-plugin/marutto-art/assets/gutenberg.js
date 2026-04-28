/* global MaruttoArt, wp */
(function () {
  'use strict';

  var el        = wp.element.createElement;
  var useState  = wp.element.useState;
  var useEffect = wp.element.useEffect;
  var useRef    = wp.element.useRef;
  var Fragment  = wp.element.Fragment;

  var registerPlugin = wp.plugins.registerPlugin;
  var PluginSidebar  = wp.editPost.PluginSidebar;
  var TextControl    = wp.components.TextControl;
  var Button         = wp.components.Button;
  var Spinner        = wp.components.Spinner;
  var Notice         = wp.components.Notice;
  var SelectControl  = wp.components.SelectControl;

  var insertBlocks = wp.data.dispatch('core/block-editor').insertBlocks;
  var createBlock  = wp.blocks.createBlock;

  var API = MaruttoArt.apiBase;

  var TABS = [
    { key: 'materials', label: '公式素材' },
    { key: 'community', label: 'みんなの作品' },
    { key: 'calendar',  label: 'カレンダー' },
  ];

  var MONTH_OPTIONS = [{ label: '全月', value: '0' }].concat(
    Array.from({ length: 12 }, function (_, i) {
      return { label: (i + 1) + '月', value: String(i + 1) };
    })
  );

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

  // ---- ページネーション ----
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
    var _tab     = useState('materials');
    var tab      = _tab[0]; var setTab     = _tab[1];
    var _query   = useState('');
    var query    = _query[0]; var setQuery   = _query[1];
    var _input   = useState('');
    var inputVal = _input[0]; var setInputVal = _input[1];
    var _month   = useState(String(new Date().getMonth() + 1));
    var month    = _month[0]; var setMonth   = _month[1];
    var _items   = useState([]);
    var items    = _items[0]; var setItems   = _items[1];
    var _pg      = useState(null);
    var pagination = _pg[0]; var setPg      = _pg[1];
    var _page    = useState(1);
    var page     = _page[0]; var setPage    = _page[1];
    var _loading = useState(false);
    var loading  = _loading[0]; var setLoading = _loading[1];
    var _error   = useState('');
    var error    = _error[0]; var setError   = _error[1];
    var _sel     = useState(null);
    var selected = _sel[0]; var setSelected = _sel[1];

    var mounted = useRef(true);
    useEffect(function () {
      return function () { mounted.current = false; };
    }, []);

    useEffect(function () {
      loadData(tab, query, page, month);
    }, [tab, query, page, month]);

    function loadData(t, q, p, mo) {
      setLoading(true);
      setError('');
      setItems([]);
      setPg(null);

      var url;
      if (t === 'community') {
        url = API + '/community-artworks?page=' + p + '&per_page=24';
        if (q) url += '&q=' + encodeURIComponent(q);
      } else if (t === 'calendar') {
        url = API + '/everyone-calendars?page=' + p + '&per_page=24';
        if (mo && mo !== '0') url += '&month=' + mo;
      } else {
        url = q
          ? API + '/search?q=' + encodeURIComponent(q) + '&page=' + p + '&per_page=24'
          : API + '/materials?page=' + p + '&per_page=24';
      }

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

    function onTabChange(key) {
      setTab(key);
      setQuery('');
      setInputVal('');
      setPage(1);
      setSelected(null);
    }

    function onSearch() {
      setQuery(inputVal.trim());
      setPage(1);
    }

    function onPage(p) {
      setPage(p);
      setSelected(null);
    }

    function onInsert() {
      if (!selected) return;
      var src = (selected.images && (selected.images.original || selected.images.webp_small)) || '';
      insertBlocks(createBlock('core/image', { url: src, alt: selected.title }));
      setSelected(null);
    }

    // タブボタン行
    var tabBar = el('div', { className: 'marutto-tabs' },
      TABS.map(function (t) {
        return el('button', {
          key:       t.key,
          type:      'button',
          className: 'marutto-tab-btn' + (tab === t.key ? ' is-active' : ''),
          onClick:   function () { onTabChange(t.key); },
        }, t.label);
      })
    );

    // 検索バー（公式素材・みんなの作品）
    var searchBar = tab !== 'calendar'
      ? el('div', { className: 'marutto-search-bar' },
          el(TextControl, {
            value:               inputVal,
            onChange:            setInputVal,
            placeholder:         'キーワードで検索...',
            onKeyDown:           function (e) { if (e.key === 'Enter') onSearch(); },
            hideLabelFromVision: true,
            label:               '検索',
          }),
          el(Button, { variant: 'secondary', isSmall: true, onClick: onSearch }, '検索')
        )
      : null;

    // 月セレクター（カレンダー）
    var monthSelect = tab === 'calendar'
      ? el('div', { className: 'marutto-month-select' },
          el('span', {}, '月を選択:'),
          el(SelectControl, {
            value:               month,
            options:             MONTH_OPTIONS,
            onChange:            function (val) { setMonth(val); setPage(1); setSelected(null); },
            hideLabelFromVision: true,
            label:               '月',
          })
        )
      : null;

    var insertLabel = selected
      ? '「' + selected.title + '」を挿入'
      : '素材を選択してください';

    return el('div', { className: 'marutto-sidebar' },
      tabBar,
      searchBar,
      monthSelect,
      loading && el('div', { className: 'marutto-status' }, el(Spinner)),
      error && el(Notice, { status: 'error', isDismissible: false }, error),
      !loading && !error && items.length === 0
        ? el('div', { className: 'marutto-status' }, '素材が見つかりません。')
        : el(Grid, { items: items, selected: selected, onSelect: setSelected }),
      el(Pager, { pagination: pagination, onPage: onPage }),
      el(Button, {
        variant:  'primary',
        disabled: !selected,
        onClick:  onInsert,
        style:    { marginTop: '10px', width: '100%', justifyContent: 'center' },
      }, insertLabel)
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
