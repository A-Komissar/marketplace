<?php

namespace App\Services;

use App\Mail\ActEmail;
use App\Models\Act;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderProduct;
use App\Models\Seller;
use DateTime;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;
use PhpOffice\PhpWord\TemplateProcessor;

class ActService
{

    private $order_completed_statuses;
    private $act_template_link;

    public function __construct() {
        $this->order_completed_statuses = Config::get('market.order_completed_statuses');
        $this->act_template_link = Config::get('app.act_template_link');
    }

    public function getActs($seller_id = null, $is_email_sent = true) {
        $query = Act::query();
        if($seller_id) {
            $query->where('seller_id', $seller_id);
        }
        $query->where('is_email_sent', $is_email_sent);
        $query->orderBy('created_at', 'desc');
        return $query->get();
    }

    public function createActs($sellers = null, $start_date = null, $end_date = null, $is_monthly_act = true) {
        $acts = array();
        foreach ($sellers as $seller) {
            try {
                if (count(Seller::where('id', $seller)->get()) > 0) {
                    $act = new Act();
                    $act->seller_id = $seller;
                    $act->start_date = $start_date;
                    $act->end_date = $end_date;
                    $act->is_monthly_act = $is_monthly_act;
                    $act->save();
                    array_push($acts, $act);
                    $act->file = $this->generateActDocument($act);
                    $act->save();
                }
            } catch (\Exception $e) { }
        }
        return $acts;
    }

    public function deleteAct($act_id) {
        try {
            $act = Act::where('id', $act_id)->first();
            try {
                unlink(storage_path('app/sellers/'.$act->seller_id.'/acts/'.$act->file));
            } catch (\Exception $e) { }
            Act::where('id', $act_id)->delete();
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function deleteActs($items) {
        foreach ($items as $item) {
            $this->deleteAct($item);
        }
    }

    public function deleteAllActsWithSeller($seller_id) {
        foreach (Act::where('seller_id', $seller_id)->get() as $act) {
            $this->deleteAct($act->id);
        }
    }

    public function sendActs($items) {
        foreach ($items as $item) {
            try {
                $act = Act::where('id',$item)->first();
                $seller = Seller::where('id', $act->seller_id)->first();
                if (Config::get('app.env') == 'production') {
                    Mail::to($seller->email)->send(new ActEmail($seller->name, $act));
                }
                $act->is_email_sent = true;
                $act->save();
            } catch (\Exception $e) { }
        }
    }

    private function generateActDocument($act) {
        try {
            $filename = 'act_'.$act->id.'.docx';
            $path = '../storage/app/sellers/'.$act->seller_id.'/acts';
            $seller = Seller::with('extra')->where('id', $act->seller_id)->first();
            $extra = $seller->extra()->first();
            $query= Order::query()->with('products');
            $query->whereIn('status_id', $this->order_completed_statuses);
            $query->where('completed_at', '>=', $act->start_date)->where('completed_at', '<=', $act->end_date);
            $query->whereHas('products', function ($q) use ($seller) {
                $q->where('seller_id', $seller->id);
            });
            $orders = $query->get();
            $table_values = array();
            foreach ($orders as $order) {
                foreach (OrderProduct::with('product')->where('order_id', $order->id)->get() as $product) {
                    try {
                        $category_id = $product->product ? $product->product->rozetka_category_id : 0;
                        $category = Category::where('id', $category_id)->first();
                        $category_name = $category ? $category->name : 'Інше';
                        if (isset($table_values[$category_id])) {
                            $table_values[$category_id]['table_count'] += $product->quantity;
                            $table_values[$category_id]['table_sum'] += $product->price * $product->quantity;
                            $table_values[$category_id]['table_commission_sum'] += $product->commission_value;
                        } else {
                            $table_values[$category_id] = [
                                'table_category' => $category_name,
                                'table_count' => $product->quantity,
                                'table_sum' => $product->price * $product->quantity,
                                'table_commission_sum' => $product->commission_value,
                                'table_commission' => 0
                            ];
                        }
                    } catch (\Exception $e) { }
                }
            }
            $total_sum = 0;
            $total_commission_sum = 0;
            foreach ($table_values as $key => $item) {
                $table_values[$key]['table_commission'] = round(($table_values[$key]['table_commission_sum']/$table_values[$key]['table_sum'])*100);
                $total_sum += $table_values[$key]['table_sum'];
                $total_commission_sum  += $table_values[$key]['table_commission_sum'];
                $table_values[$key]['table_commission_sum'] = str_replace('.', ',', strval($table_values[$key]['table_commission_sum']));
                $table_values[$key]['table_sum'] = str_replace('.', ',', strval($table_values[$key]['table_sum']));
            }
            array_push($table_values, [
                'table_category' => 'Всього',
                'table_count' => '-',
                'table_sum' => $total_sum,
                'table_commission' => '-',
                'table_commission_sum' => $total_commission_sum
            ]);
            $table_values = array_values($table_values);

            if (!$seller) return null;
            // create folder if not exists
            if(!is_dir($path)){
                mkdir($path, 0755, true);
            }
            // generate document
            $act_date = (new DateTime($act->end_date))->format('d.m.Y'); // (new DateTime($act->updated_at))->format('d.m.Y');
            $template = new TemplateProcessor($this->act_template_link);
            $template->setValue('act_id', $act->id);
            $template->setValue('act_date', $act_date);
            if ($act->is_monthly_act) {
                $month = $this->number2month(intval(date("m", strtotime($act->end_date))));
                $year = date("Y", strtotime($act->end_date));
                $act_period = $month.' '.$year.' р.';
            } else {
                $act_period = 'період від '.(new DateTime($act->start_date))->format('d.m.Y')
                .' до '.(new DateTime($act->end_date))->format('d.m.Y');
            }
            $template->setValue('act_period', $act_period);
            $template->setValue('seller_contract_number', $extra->contract_number);
            $template->setValue('seller_contract_date', (new DateTime($extra->contract_date))->format('d.m.Y'));
            $template->setValue('seller_legal_name_long', $extra->legal_name_long);
            $template->setValue('seller_legal_name_short', $extra->legal_name_short);
            $template->setValue('seller_legal_code_text', $extra->legal_code_text);
            $template->setValue('seller_legal_info_text', $extra->legal_info_text);
            $template->setValue('act_seller_signature_name', $extra->act_signature_name);
            $template->setValue('act_seller_signature_decoding', $extra->act_signature_decoding);
            $template->cloneRowAndSetValues('table_category', $table_values);
            $template->setValue('total_sum', str_replace('.', ',', strval($total_commission_sum)));
            $template->setValue('total_sum_text', $this->number2string($total_commission_sum));

            $template->saveAs($path.'/'.$filename);
            return $filename;
        } catch (\Exception $e) {
            return null;
        }
    }

    private function number2string($number) {
        $fraction = round(($number - floor($number))*100);
        $number = intval($number);

        // обозначаем словарь в виде статической переменной функции, чтобы
        // при повторном использовании функции его не определять заново
        static $dic = array(

            // словарь необходимых чисел
            array(
                -2	=> 'дві',
                -1	=> 'одна',
                1	=> 'одна',
                2	=> 'дві',
                3	=> 'три',
                4	=> 'чотири',
                5	=> 'п\'ять',
                6	=> 'шість',
                7	=> 'сім',
                8	=> 'вісім',
                9	=> 'дев\'ять',
                10	=> 'десять',
                11	=> 'одинадцять',
                12	=> 'дванадцять',
                13	=> 'тринадцать',
                14	=> 'чотирнадцять' ,
                15	=> 'п\'ятнадцать',
                16	=> 'шістнадцять',
                17	=> 'сімнадцять',
                18	=> 'вісімнадцять',
                19	=> 'дев\'ятнадцать',
                20	=> 'двадцать',
                30	=> 'тридцать',
                40	=> 'сорок',
                50	=> 'п\'ятдесят',
                60	=> 'шістдесят',
                70	=> 'сімдесят',
                80	=> 'вісімдесят',
                90	=> 'дев\'яносто',
                100	=> 'сто',
                200	=> 'двісті',
                300	=> 'триста',
                400	=> 'чотириста',
                500	=> 'п\'ятсот',
                600	=> 'шістсот',
                700	=> 'сімсот',
                800	=> 'вісімсот',
                900	=> 'дев\'ятсот'
            ),

            // словарь порядков со склонениями для плюрализации
            array(
                array('гривня', 'гривні', 'гривень'),
                array('тисяча', 'тисячі', 'тисяч'),
                array('мільйон', 'мільйони', 'мільйонів'),
                array('мільярд', 'мільярди', 'мільярдів'),
                array('трильйон', 'трильйони', 'трильйонів'),
                array('квадрильйон', 'квадрильйони', 'квадрильйонів'),
                // квинтиллион, секстиллион и т.д.
            ),

            // карта плюрализации
            array(
                2, 0, 1, 1, 1, 2
            )
        );

        // обозначаем переменную в которую будем писать сгенерированный текст
        $string = array();

        // дополняем число нулями слева до количества цифр кратного трем,
        // например 1234, преобразуется в 001234
        $number = str_pad($number, ceil(strlen($number)/3)*3, 0, STR_PAD_LEFT);

        // разбиваем число на части из 3 цифр (порядки) и инвертируем порядок частей,
        // т.к. мы не знаем максимальный порядок числа и будем бежать снизу
        // единицы, тысячи, миллионы и т.д.
        $parts = array_reverse(str_split($number,3));

        // бежим по каждой части
        foreach($parts as $i=>$part) {

            // если часть не равна нулю, нам надо преобразовать ее в текст
            if($part>0) {

                // обозначаем переменную в которую будем писать составные числа для текущей части
                $digits = array();

                // если число треххзначное, запоминаем количество сотен
                if($part>99) {
                    $digits[] = floor($part/100)*100;
                }

                // если последние 2 цифры не равны нулю, продолжаем искать составные числа
                // (данный блок прокомментирую при необходимости)
                if($mod1=$part%100) {
                    $mod2 = $part%10;
                    $flag = $i==1 && $mod1!=11 && $mod1!=12 && $mod2<3 ? -1 : 1;
                    if($mod1<20 || !$mod2) {
                        $digits[] = $flag*$mod1;
                    } else {
                        $digits[] = floor($mod1/10)*10;
                        $digits[] = $flag*$mod2;
                    }
                }

                // берем последнее составное число, для плюрализации
                $last = abs(end($digits));

                // преобразуем все составные числа в слова
                foreach($digits as $j=>$digit) {
                    $digits[$j] = $dic[0][$digit];
                }

                // добавляем обозначение порядка или валюту
                $digits[] = $dic[1][$i][(($last%=100)>4 && $last<20) ? 2 : $dic[2][min($last%10,5)]];

                // объединяем составные числа в единый текст и добавляем в переменную, которую вернет функция
                array_unshift($string, join(' ', $digits));
            }
        }

        // преобразуем переменную в текст и возвращаем из функции, ура!
        $result = join(' ', $string);

        // переводим копейки
        $coins_text = ' копійок';
        $fraction_last_num = substr(strval($fraction), -1);
        if ($fraction_last_num == '1') {
            $coins_text = ' копійка';
        } else if ($fraction_last_num == '2' || $fraction_last_num == '3' || $fraction_last_num == '4') {
            $coins_text = ' копійки';
        }

        return $result.' '.$fraction.$coins_text;
    }

    private function number2month($number) {
        switch ($number) {
            default:
            case 1:
                return 'січень';
                break;
            case 2:
                return 'лютий';
                break;
            case 3:
                return 'березень';
                break;
            case 4:
                return 'квітень';
                break;
            case 5:
                return 'травень';
                break;
            case 6:
                return 'червень';
                break;
            case 7:
                return 'липень';
                break;
            case 8:
                return 'серпень';
                break;
            case 9:
                return 'вересень';
                break;
            case 10:
                return 'жовтень';
                break;
            case 11:
                return 'листопад';
                break;
            case 12:
                return 'грудень';
                break;
        }
    }

}
