<!doctype html>
<html lang="ru">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Стандартный HTML — Telegram</title>
  <style>
    :root {
      color-scheme: light;
      --ink: #182230;
      --muted: #69788b;
      --line: #dfe7ef;
      --paper: #ffffff;
      --canvas: #f4f7fb;
      --navy: #111d2f;
      --blue: #248be6;
      --blue-dark: #1574c6;
      --blue-soft: #eaf5ff;
      --cyan: #62d5ef;
      --shadow: 0 20px 60px rgba(31, 62, 95, .12);
    }
    * { box-sizing: border-box; }
    html { font-size: 16px; }
    body { min-width:320px; margin:0; background:radial-gradient(circle at 8% 0,#e8f6ff 0,transparent 30%),var(--canvas); color:var(--ink); font:16px/1.5 Inter,-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Arial,sans-serif; }
    h1,h2,p { margin:0; }
    h1 { margin-bottom:12px; font-size:clamp(2rem,5vw,3.7rem); line-height:1.03; letter-spacing:-.045em; }
    code,pre { font-family:"SFMono-Regular",Consolas,"Liberation Mono",monospace; }
    .tg-reference { width:min(1160px,calc(100% - 32px)); margin:0 auto; padding:42px 0 64px; }
    .tg-reference__header { position:relative; display:flex; align-items:flex-start; justify-content:space-between; gap:28px; margin-bottom:24px; padding:38px 40px; overflow:hidden; border-radius:28px; background:linear-gradient(135deg,var(--navy),#163656 68%,#165786); color:#fff; box-shadow:var(--shadow); }
    .tg-reference__header::before { content:"&lt;/&gt;"; position:absolute; right:38px; bottom:-38px; color:rgba(255,255,255,.07); font:bold 150px/1 Consolas,monospace; letter-spacing:-.13em; transform:rotate(-7deg); pointer-events:none; }
    .tg-reference__header > * { position:relative; z-index:1; }
    .tg-reference__eyebrow { display:inline-flex; align-items:center; gap:8px; margin-bottom:12px; color:#9fe9fa; font-size:.78rem; font-weight:800; letter-spacing:.12em; text-transform:uppercase; }
    .tg-reference__eyebrow::before { content:""; width:9px; height:9px; border-radius:50%; background:var(--cyan); box-shadow:0 0 0 5px rgba(98,213,239,.14); }
    .tg-reference__description { max-width:700px; color:#d7e9f8; font-size:1.06rem; }
    .tg-reference__description code { padding:3px 7px; border:1px solid rgba(255,255,255,.15); border-radius:6px; background:rgba(255,255,255,.1); color:#fff; font-size:.83em; }
    .badge { display:inline-flex; align-items:center; padding:8px 12px; border:1px solid rgba(255,255,255,.18); border-radius:999px; background:rgba(255,255,255,.1); color:#dff7ff; font:700 .78rem/1 Consolas,monospace; white-space:nowrap; backdrop-filter:blur(8px); }
    .box { overflow:hidden; border:1px solid rgba(197,212,227,.8); border-radius:22px; background:var(--paper); box-shadow:0 12px 36px rgba(31,62,95,.08); }
    .box-title { display:flex; align-items:center; justify-content:space-between; gap:20px; padding:23px 25px; border-bottom:1px solid var(--line); background:linear-gradient(180deg,#fff,#fbfdff); }
    .box-title h2 { margin-bottom:6px; font-size:1.25rem; letter-spacing:-.02em; }
    .box-title .tg-preview { padding:0; box-shadow:none; background:transparent; color:var(--muted); font-size:.82rem; }
    .btn { display:inline-flex; align-items:center; justify-content:center; flex:none; padding:9px 13px; border:1px solid #b9d8f2; border-radius:10px; background:var(--blue-soft); color:var(--blue-dark); font-size:.82rem; font-weight:750; text-decoration:none; transition:.18s ease; }
    .btn:hover { border-color:var(--blue); background:#dcedfd; transform:translateY(-1px); }
    .table-wrap { overflow-x:auto; }
    .table { width:100%; min-width:780px; border-collapse:collapse; }
    .table th { padding:13px 20px; background:#f7fafd; color:#637589; font-size:.72rem; font-weight:800; letter-spacing:.08em; text-align:left; text-transform:uppercase; }
    .table th,.table td { border-bottom:1px solid var(--line); vertical-align:top; }
    .table td { padding:18px 20px; }
    .table tbody tr:last-child td { border-bottom:0; }
    .table tbody tr { transition:background-color .15s ease; }
    .table tbody tr:hover { background:#fafdff; }
    .table th:first-child,.table td:first-child { width:48%; }
    .tg-syntax { display:flex; flex-direction:column; align-items:flex-start; gap:7px; }
    .tg-syntax code { display:inline-block; max-width:100%; padding:5px 8px; overflow-wrap:anywhere; white-space:pre-wrap; border:1px solid #d9e2eb; border-radius:7px; background:#f2f5f8; color:#33475b; font-size:.8rem; line-height:1.55; }
    .tg-syntax small { color:var(--muted); font-size:.8rem; }
    .tg-preview { max-width:440px; padding:11px 14px; border:1px solid #d8e9f6; border-radius:13px 13px 4px 13px; background:linear-gradient(135deg,#eaf6ff,#e3f2ff); color:#203346; box-shadow:0 3px 10px rgba(47,111,162,.08); }
    .tg-preview code { padding:2px 5px; border-radius:5px; background:#d5e7f5; color:#26465e; font-size:.8rem; }
    .tg-preview pre { margin:0; padding:10px; overflow:auto; border-radius:8px; background:#d5e7f5; color:#26465e; font-size:.78rem; white-space:pre-wrap; }
    .tg-preview .spoiler { border-radius:4px; background:#90a7b9; color:transparent; user-select:none; }
    .tg-preview .link,.tg-preview .mention { color:#117ecc; font-weight:700; }
    .tg-preview .time,.tg-preview .muted { color:#6d8395; font-size:.8rem; }
    .tg-preview .emoji { display:inline-grid; width:1.55em; height:1.55em; place-items:center; border-radius:50%; background:linear-gradient(135deg,#ffe36b,#ffab73); vertical-align:-.25em; box-shadow:0 1px 4px rgba(160,97,17,.18); }
    .tg-preview .quote { margin:0; padding-left:11px; border-left:3px solid var(--blue); color:#2e4b62; }
    .tg-preview .expand { margin-top:8px; color:#137fcf; font-size:.8rem; font-weight:750; }
    .tg-reference__footer { margin:18px 4px 0; color:var(--muted); font-size:.83rem; }
    .tg-reference__footer code { padding:2px 5px; border:1px solid #d8e1e9; border-radius:5px; background:#fff; color:#33475b; }
    @media (max-width:700px) { .tg-reference { width:min(100% - 20px,1160px); padding:16px 0 38px; } .tg-reference__header { flex-direction:column; padding:28px 23px; border-radius:20px; } .tg-reference__header::before { right:15px; font-size:100px; } .box { border-radius:16px; } .box-title { align-items:flex-start; flex-direction:column; padding:18px; } .table th,.table td { padding:13px 14px; } }
    @media print { body { background:#fff; } .tg-reference { width:100%; padding:0; } .tg-reference__header,.box { box-shadow:none; } .btn { display:none; } }
  </style>
</head>
<body class="layout-page">
  <main class="tg-reference">
    <header class="tg-reference__header">
      <div>
        <div class="tg-reference__eyebrow">Telegram Bot API</div>
        <h1>Стандартный HTML</h1>
        <p class="tg-reference__description">Теги, которые можно передать в параметре <code>parse_mode=HTML</code> при отправке обычных сообщений и постов в Telegram.</p>
      </div>
      <span class="badge tg-reference__version">parse_mode = HTML</span>
    </header>

    <section class="box" aria-labelledby="tags-title">
      <div class="box-title">
        <div><h2 id="tags-title" class="h3">Поддерживаемые теги</h2><span class="tg-preview muted">Нажмите на спойлер в Telegram, чтобы открыть его</span></div>
        <a class="btn" href="https://core.telegram.org/bots/api#html-style">Документация Telegram ↗</a>
      </div>
      <div class="table-wrap">
        <table class="table">
          <thead><tr><th>Тег / синтаксис</th><th>Как выглядит в Telegram</th></tr></thead>
          <tbody>
            <tr><td><div class="tg-syntax"><code>&lt;b&gt;Важное сообщение&lt;/b&gt;</code><small>Аналог: &lt;strong&gt;Важное сообщение&lt;/strong&gt;</small></div></td><td><div class="tg-preview"><b>Важное сообщение</b></div></td></tr>
            <tr><td><div class="tg-syntax"><code>&lt;i&gt;Небольшое уточнение&lt;/i&gt;</code><small>Аналог: &lt;em&gt;Небольшое уточнение&lt;/em&gt;</small></div></td><td><div class="tg-preview"><i>Небольшое уточнение</i></div></td></tr>
            <tr><td><div class="tg-syntax"><code>&lt;u&gt;Подчёркнутый фрагмент&lt;/u&gt;</code><small>Аналог: &lt;ins&gt;Подчёркнутый фрагмент&lt;/ins&gt;</small></div></td><td><div class="tg-preview"><u>Подчёркнутый фрагмент</u></div></td></tr>
            <tr><td><div class="tg-syntax"><code>&lt;s&gt;Старая цена&lt;/s&gt; 990 ₽</code><small>Аналоги тега: &lt;strike&gt; и &lt;del&gt;</small></div></td><td><div class="tg-preview"><s>Старая цена</s> 990 ₽</div></td></tr>
            <tr><td><div class="tg-syntax"><code>Финал: &lt;span class=&quot;tg-spoiler&quot;&gt;главный герой спасён&lt;/span&gt;</code><small>Аналог: &lt;tg-spoiler&gt;главный герой спасён&lt;/tg-spoiler&gt;</small></div></td><td><div class="tg-preview">Финал: <span class="spoiler">главный герой спасён</span></div></td></tr>
            <tr><td><div class="tg-syntax"><code>&lt;b&gt;&lt;i&gt;Жирный курсив, &lt;s&gt;зачёркнутый&lt;/s&gt;&lt;/i&gt;&lt;/b&gt;</code><small>Форматирование можно вкладывать друг в друга</small></div></td><td><div class="tg-preview"><b><i>Жирный курсив, <s>зачёркнутый</s></i></b></div></td></tr>
            <tr><td><div class="tg-syntax"><code>Читайте &lt;a href=&quot;https://example.com&quot;&gt;подробности в статье&lt;/a&gt;</code><small>Telegram показывает URL перед открытием</small></div></td><td><div class="tg-preview">Читайте <span class="link">подробности в статье</span></div></td></tr>
            <tr><td><div class="tg-syntax"><code>Спасибо, &lt;a href=&quot;tg://user?id=123456789&quot;&gt;Алексей&lt;/a&gt;!</code><small>Упоминание пользователя по ID</small></div></td><td><div class="tg-preview">Спасибо, <span class="mention">Алексей</span>!</div></td></tr>
            <tr><td><div class="tg-syntax"><code>Отличная работа &lt;tg-emoji emoji-id=&quot;5368324170671202286&quot;&gt;👍&lt;/tg-emoji&gt;</code><small>Внутри указывается обычный emoji-резерв</small></div></td><td><div class="tg-preview">Отличная работа <span class="emoji">👍</span></div></td></tr>
            <tr><td><div class="tg-syntax"><code>Встреча: &lt;tg-time unix=&quot;1647531900&quot; format=&quot;wDT&quot;&gt;чт, 17 марта 2022 г., 22:45:00&lt;/tg-time&gt;</code><small>Формат: r, w, d/D, t/T</small></div></td><td><div class="tg-preview">Встреча: <span class="time">чт, 17 марта 2022 г., 22:45:00</span></div></td></tr>
            <tr><td><div class="tg-syntax"><code>Опубликовано &lt;tg-time unix=&quot;…&quot; format=&quot;r&quot;&gt;5 минут назад&lt;/tg-time&gt;</code><small>Относительное время</small></div></td><td><div class="tg-preview">Опубликовано <span class="time">5 минут назад</span></div></td></tr>
            <tr><td><div class="tg-syntax"><code>Дедлайн: &lt;tg-time unix=&quot;…&quot;&gt;завтра в 10:00&lt;/tg-time&gt;</code><small>Без format отображается исходный текст, но дата остаётся интерактивной</small></div></td><td><div class="tg-preview">Дедлайн: <span class="time">завтра в 10:00</span></div></td></tr>
            <tr><td><div class="tg-syntax"><code>Вызовите &lt;code&gt;sendMessage()&lt;/code&gt;</code><small>Короткий моноширинный код</small></div></td><td><div class="tg-preview">Вызовите <code>sendMessage()</code></div></td></tr>
            <tr><td><div class="tg-syntax"><code>&lt;pre&gt;const total = 42;
console.log(total);&lt;/pre&gt;</code><small>Моноширинный блок с сохранением переносов</small></div></td><td><div class="tg-preview"><pre>const total = 42;
console.log(total);</pre></div></td></tr>
            <tr><td><div class="tg-syntax"><code>&lt;pre&gt;&lt;code class=&quot;language-python&quot;&gt;# Python
print(&quot;Привет&quot;)&lt;/code&gt;&lt;/pre&gt;</code><small>Блок кода с языком подсветки</small></div></td><td><div class="tg-preview"><pre># Python
print("Привет")</pre></div></td></tr>
            <tr><td><div class="tg-syntax"><code>&lt;blockquote&gt;Хороший текст — тот, который легко читать.&lt;/blockquote&gt;</code><small>Обычная блочная цитата</small></div></td><td><div class="tg-preview"><blockquote class="quote">Хороший текст — тот, который легко читать.</blockquote></div></td></tr>
            <tr><td><div class="tg-syntax"><code>&lt;blockquote expandable&gt;Видимая часть длинной цитаты…&lt;/blockquote&gt;</code><small>Сворачиваемая цитата</small></div></td><td><div class="tg-preview"><blockquote class="quote">Видимая часть длинной цитаты…</blockquote><div class="expand">Показать полностью</div></div></td></tr>
          </tbody>
        </table>
      </div>
    </section>

    <p class="tg-reference__footer">Поддерживается только перечисленный HTML. Символы <code>&lt;</code>, <code>&gt;</code> и <code>&amp;</code> вне тегов необходимо экранировать как HTML-сущности. Список актуализирован 15 августа 2026 года.</p>
  </main>
</body>
</html>
