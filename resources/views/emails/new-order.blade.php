<x-mail::message>
# Новый заказ {{ $order->number }}

<x-mail::panel>
**Пользователь:** {{ $order->user?->name ?? 'Гость' }}<br>
**Сумма заказа:** {{ $order->amount }}<br>
**Доставка:** {{ $order->deliveryType?->title }}<br>
@if($order->comment)
**Комментарий к заказу:**
<br>{{ $order->comment }}
@endif
</x-mail::panel>

<x-mail::panel>
### Данные получателя:
**Имя и Фамилия:** {{ $order->orderCustomer?->first_name }} {{ $order->orderCustomer?->last_name }}<br>
**Телефон:** {{ $order->orderCustomer?->phone }}<br>
@if($order->orderCustomer?->messenger_url)
**Мессенджер:** [{{ $order->orderCustomer->messenger_url }}]({{ $order->orderCustomer->messenger_url }})<br>
@endif
@if($order->deliveryType?->with_address)
**Город:** {{ $order->orderCustomer?->city }}<br>
**Адрес:** {{ $order->orderCustomer?->address }}
@endif
</x-mail::panel>

### Состав заказа:

<x-mail::table>
| Товар | Цена | Кол-во | Итого |
|:---|:---:|:---:|:---:|
@foreach($order->orderItems as $item)
| {{ $item->product?->brand ?: $item->product?->name }} | {{ $item->price }} | {{ $item->quantity }} | {{ $item->amount }} |
@endforeach
</x-mail::table>

</x-mail::message>
