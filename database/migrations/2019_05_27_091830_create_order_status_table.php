<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateOrderStatusTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('order_status', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->integer('market_id');
            $table->foreign('market_id')->references('id')->on('markets')->onDelete('cascade');
            $table->integer('key')->default(0);
            $table->string('value')->default('undefined');
        });

        // insert data
        DB::table('order_status')->insert(array('market_id' => 1, 'key' => 1, 'value' => 'Новый заказ'));
        DB::table('order_status')->insert(array('market_id' => 1, 'key' => 2, 'value' => 'Данные подтверждены. Ожидает отправки'));
        DB::table('order_status')->insert(array('market_id' => 1, 'key' => 3, 'value' => 'Передан в службу доставки'));
        DB::table('order_status')->insert(array('market_id' => 1, 'key' => 4, 'value' => 'Доставляется'));
        DB::table('order_status')->insert(array('market_id' => 1, 'key' => 5, 'value' => 'Ожидает в пункте самовывоза'));
        DB::table('order_status')->insert(array('market_id' => 1, 'key' => 6, 'value' => 'Посылка получена'));
        DB::table('order_status')->insert(array('market_id' => 1, 'key' => 7, 'value' => 'Не обработан продавцом'));
        DB::table('order_status')->insert(array('market_id' => 1, 'key' => 10, 'value' => 'Отправка просрочена'));
        DB::table('order_status')->insert(array('market_id' => 1, 'key' => 11, 'value' => 'Не забрал посылку'));
        DB::table('order_status')->insert(array('market_id' => 1, 'key' => 12, 'value' => 'Отказался от товара'));
        DB::table('order_status')->insert(array('market_id' => 1, 'key' => 13, 'value' => 'Отменен Администратором'));
        DB::table('order_status')->insert(array('market_id' => 1, 'key' => 15, 'value' => 'Некорректный ТТН'));
        DB::table('order_status')->insert(array('market_id' => 1, 'key' => 16, 'value' => 'Нет в наличии/брак'));
        DB::table('order_status')->insert(array('market_id' => 1, 'key' => 17, 'value' => 'Отмена. Не устраивает оплата'));
        DB::table('order_status')->insert(array('market_id' => 1, 'key' => 18, 'value' => 'Не удалось связаться с покупателем'));
        DB::table('order_status')->insert(array('market_id' => 1, 'key' => 19, 'value' => 'Возврат'));
        DB::table('order_status')->insert(array('market_id' => 1, 'key' => 20, 'value' => 'Отмена. Не устраивает товар'));
        DB::table('order_status')->insert(array('market_id' => 1, 'key' => 24, 'value' => 'Отмена. Не устраивает доставка'));
        DB::table('order_status')->insert(array('market_id' => 1, 'key' => 25, 'value' => 'Тестовый заказ'));
        DB::table('order_status')->insert(array('market_id' => 1, 'key' => 26, 'value' => 'Обрабатывается менеджером'));
        DB::table('order_status')->insert(array('market_id' => 1, 'key' => 27, 'value' => 'Требует доукомплектации'));
        DB::table('order_status')->insert(array('market_id' => 1, 'key' => 28, 'value' => 'Некорректные контактные данные'));
        DB::table('order_status')->insert(array('market_id' => 1, 'key' => 29, 'value' => 'Отмена. Некорректная цена на сайте'));
        DB::table('order_status')->insert(array('market_id' => 1, 'key' => 30, 'value' => 'Истек срок резерва'));
        DB::table('order_status')->insert(array('market_id' => 1, 'key' => 31, 'value' => 'Отмена. Заказ восстановлен'));
        DB::table('order_status')->insert(array('market_id' => 1, 'key' => 32, 'value' => 'Отмена. Не устраивает разгруппировка заказа'));
        DB::table('order_status')->insert(array('market_id' => 1, 'key' => 33, 'value' => 'Отмена. Не устраивает стоимость доставки'));
        DB::table('order_status')->insert(array('market_id' => 1, 'key' => 34, 'value' => 'Отмена. Не устраивает перевозчик, способ доставки'));
        DB::table('order_status')->insert(array('market_id' => 1, 'key' => 35, 'value' => 'Отмена. Не устраивают сроки доставки'));
        DB::table('order_status')->insert(array('market_id' => 1, 'key' => 36, 'value' => 'Отмена. Клиент хочет оплату по безналу. У продавца нет такой возможности'));
        DB::table('order_status')->insert(array('market_id' => 1, 'key' => 37, 'value' => 'Отмена. Не устраивает предоплата'));
        DB::table('order_status')->insert(array('market_id' => 1, 'key' => 38, 'value' => 'Отмена. Не устраивает качество товара'));
        DB::table('order_status')->insert(array('market_id' => 1, 'key' => 39, 'value' => 'Отмена. Не подошли характеристики товара (цвет,размер)'));
        DB::table('order_status')->insert(array('market_id' => 1, 'key' => 40, 'value' => 'Отмена. Клиент передумал'));
        DB::table('order_status')->insert(array('market_id' => 1, 'key' => 41, 'value' => 'Отмена. Купил на другом сайте'));
        DB::table('order_status')->insert(array('market_id' => 1, 'key' => 42, 'value' => 'Нет в наличии'));
        DB::table('order_status')->insert(array('market_id' => 1, 'key' => 43, 'value' => 'Брак'));
        DB::table('order_status')->insert(array('market_id' => 1, 'key' => 44, 'value' => 'Отмена. Фейковый заказ'));
        DB::table('order_status')->insert(array('market_id' => 1, 'key' => 45, 'value' => 'Отменен покупателем'));
        DB::table('order_status')->insert(array('market_id' => 1, 'key' => 46, 'value' => 'Восстановлен при прозвоне'));
        DB::table('order_status')->insert(array('market_id' => 1, 'key' => 47, 'value' => 'Обрабатывается менеджером (не удалось связаться 1-ый раз)'));
        DB::table('order_status')->insert(array('market_id' => 1, 'key' => 48, 'value' => 'Обрабатывается менеджером (не удалось связаться 2-ой раз'));
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('order_status');
    }
}
