{{-- Обёртка вокруг telegram-standard-html-tags-frame.blade.php — рендерится
     внутри MoonShine (см. VkPostFormPage::fields(), Collapse + FlexibleRender).
     Настоящий iframe с srcdoc — единственный способ изолировать CSS этой
     справки от MoonShine в обе стороны: обычное скоупирование селекторов
     защищает только от утечки НАРУЖУ, а Preflight/Tailwind-стили MoonShine
     всё равно продолжают влиять на элементы ВНУТРИ фрагмента. srcdoc — тот
     же origin, что и родительская страница, поэтому contentDocument доступен
     без CORS-ограничений.

     Collapse, в котором лежит этот блок, по умолчанию закрыт и скрывает
     содержимое через display:none (collapse.blade.php), поэтому разовое
     чтение scrollHeight в onload дало бы 0. ResizeObserver на body фрейма
     корректно пересчитывает высоту и в момент открытия аккордеона (когда
     предок переключается display:none → block), и при любом позднем
     реflow (например, догрузке шрифта).

     ->render() обязателен: Illuminate\View\View реализует Htmlable, и {{ }}
     под этот контракт вообще не экранирует значение (оно и задумано для
     вставки чужого HTML как есть). srcdoc — HTML-атрибут, поэтому нужен
     именно экранированный текст; ->render() отдаёт plain string, для
     которого {{ }} снова честно вызывает htmlspecialchars(). Без этого
     браузер обрывает атрибут на первой же кавычке внутри фрейма
     (lang="ru" и т.п.), и содержимое просто не рендерится. --}}
<iframe
    title="Стандартный HTML — Telegram"
    style="width:100%;height:100%;border:0;display:block;"
    srcdoc="{{ view('pages.telegram-standard-html-tags-frame')->render() }}"
    onload="
        var doc = this.contentDocument;
        var frame = this;
        new ResizeObserver(function (entries) {
            frame.style.height = Math.ceil(entries[0].contentRect.height + 24) + 'px';
        }).observe(doc.body);
    "
></iframe>
