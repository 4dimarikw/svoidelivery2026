<div id="order-deliveries">
    <p>Повтор отправляет сохранённый файл. Новый XML формируется из сохранённых данных заказа. Обновите страницу, чтобы увидеть результат загрузки.</p>
    @if($newButton)
        {!! $newButton->render() !!}
    @endif
    <div class="overflow-x-auto">
        <table class="table">
            <thead><tr><th>Версия</th><th>Состояние</th><th>Попытки: всего / цикл</th><th>Последняя загрузка FTP</th><th>Следующая попытка</th><th>Ошибка</th><th>Действие</th></tr></thead>
            <tbody>
            @forelse($deliveries as $delivery)
                <tr>
                    <td>{{ $delivery->version }}{{ $delivery->source === 'legacy' ? ' (создана при переходе)' : '' }}</td>
                    <td>{{ ['pending' => 'Ожидает', 'processing' => 'Отправляется', 'retry' => 'Ожидает повтора', 'sent' => 'Загружено на FTP', 'failed' => 'Ошибка'][$delivery->state] ?? $delivery->state }}</td>
                    <td>{{ $delivery->attempts }} / {{ $delivery->cycle_attempts }}</td>
                    <td>{{ $delivery->sent_at?->format('d.m.Y H:i:s') ?? '—' }}</td>
                    <td>{{ $delivery->next_attempt_at?->format('d.m.Y H:i:s') ?? '—' }}</td>
                    <td>{{ $delivery->last_error ?? '—' }}</td>
                    <td>{!! isset($retryButtons[$delivery->id]) ? $retryButtons[$delivery->id]->render() : '' !!}</td>
                </tr>
            @empty
                <tr><td colspan="7">Сохранённых XML нет. Можно создать новый файл из текущих данных заказа.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
