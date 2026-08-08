<?php

namespace Domain\Order\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Infrastructure\Rules\FIORule;
use Support\Rules\RussianPhoneNumber;

class OrderRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->check();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            // Способ доставки/оплаты клиент больше не выбирает — публичное
            // оформление заказа фиксировано на «Служба доставки»/«При
            // получении» (OrderController::store(), config/order.php).
            // delivery_type_id/payment_method_id в теле запроса, если
            // пришли, здесь не валидируются и контроллером не читаются.
            // Раз доставка всегда с адресом, address_id — required.

            // Владение адресом проверяется уже здесь (defense in depth) —
            // второй раз в Processes\AssignCustomer, тот же паттерн, что
            // Account\AddressController.
            'address_id' => [
                'required',
                'int',
                Rule::exists('addresses', 'id')->where('user_id', $this->user()?->id),
            ],

            // Плоские поля, не вложенный customer[...] — у <x-ui.input> имя
            // одновременно и HTML-атрибут name, и ключ для old()/$errors,
            // а точечная нотация ('customer.first_name') не эквивалентна
            // HTML-скобкам ('customer[first_name]'), которые нужны PHP для
            // разбора вложенного массива. Ни один другой форме в проекте
            // так не делает — не изобретаю первый прецедент.
            'first_name' => ['required', new FIORule],
            'last_name' => ['required', new FIORule],
            'phone' => ['required', 'string', new RussianPhoneNumber],

            'comment' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function attributes(): array
    {
        return [
            'first_name' => 'Имя',
            'last_name' => 'Фамилия',
            'phone' => 'Телефон',
            'address_id' => 'Адрес',
            'comment' => 'Комментарий',
        ];
    }
}
