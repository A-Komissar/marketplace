<?php

namespace App\Services;

use App\Mail\BalanceRemindEmail;
use App\Models\TransactionHistory;
use App\Models\Admin;
use App\Models\Seller;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Input;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class FinanceService
{

    public function getBalance($seller_id) {
        $seller = Seller::select('balance')->where('id', $seller_id)->first();
        return $seller->balance;
    }

    public function getTransactionHistory($seller_id, Request $request = null) {
        $query = TransactionHistory::query();
        $query->where('seller_id', $seller_id);

        $query->when($request->has('start_date'), function ($q) {
            return $q->where('created_at', '>=', request('start_date'));
        });

        $query->when($request->has('end_date'), function ($q) {
            return $q->where('created_at', '<=', request('end_date'));
        });

        $query->when($request->has('type'), function ($q) {
            if(request('type') == 'sub') {
                return $q->where('transaction_value', '<', '0');
            } else if(request('type') == 'add') {
                return $q->where('transaction_value', '>', '0');
            }
        });

        $query->orderBy('id', 'desc');
        $transactions = $query->paginate(50)->appends(Input::except('page'));
        return $transactions;
    }

    public function updateBalance($seller_id, $admin_id, $transaction_value, $transaction_description) {
        DB::beginTransaction();
        try {
            $balance_before = $this->getBalance($seller_id);
            // update sellers table
            $seller = Seller::where('id', $seller_id)->first();
            $seller->balance += $transaction_value;
            $seller->push();
            // update transaction_history table
            $transaction = new TransactionHistory;
            $transaction->seller_id = $seller_id;
            $transaction->admin_id = $admin_id;
            $transaction->description = $transaction_description;
            $transaction->transaction_value = $transaction_value;
            $transaction->balance_before = $balance_before;
            $transaction->balance_after = $seller->balance;
            $transaction->push();

            try {
                $email_resend_time = config('market.seller_balance_email_resend_time');
                if ($seller->balance <= $seller->balance_remind_sum && $email_resend_time > 0
                    && strtotime($seller->last_balance_remind_time) < (time()-(60*60*$email_resend_time))) {
                    $this->sendBalanceRemindMail($seller_id);
                }
            } catch (\Exception $e) {}

            DB::commit();
            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            return false;
        }
    }

    public function getTransactionStatistics($seller_id = null, Request $request = null) {
        $statistics = new \stdClass();
        $statistics->input = $this->getTransactionSum('input', $seller_id, $request);
        $statistics->output = $this->getTransactionSum('output', $seller_id, $request);
        $statistics->sum = $this->getTransactionSum(null, $seller_id, $request);
        return $statistics;
    }

    private function getTransactionSum($type = null, $seller_id = null, Request $request = null) {
        $query = TransactionHistory::query();
        if ($seller_id) {
            $query->where('seller_id', $seller_id);
        }
        $query->when($request->has('start_date'), function ($q) {
            return $q->where('created_at', '>=', request('start_date'));
        });
        $query->when($request->has('end_date'), function ($q) {
            return $q->where('created_at', '<=', request('end_date'));
        });
        if ($type == 'input') {
            $query->where('admin_id', '<>', Admin::where('name', 'auto')->first()->id);
            $query->where('transaction_value', '>', 0);
        } else if ($type == 'output') {
            $query->where('admin_id', '<>', Admin::where('name', 'auto')->first()->id);
            $query->where('transaction_value', '<', 0);
            $sum1 = $query->sum('transaction_value');
            $query2 = TransactionHistory::query();
            if ($seller_id) {
                $query2->where('seller_id', $seller_id);
            }
            $query2->when($request->has('start_date'), function ($q) {
                return $q->where('created_at', '>=', request('start_date'));
            });
            $query2->when($request->has('end_date'), function ($q) {
                return $q->where('created_at', '<=', request('end_date'));
            });
            $sum2 = $query2->where('admin_id', '=', Admin::where('name', 'auto')->first()->id)->sum('transaction_value');
            return $sum1 + $sum2;
        }
         return $query->sum('transaction_value');
    }

    private function sendBalanceRemindMail($seller_id) {
        $seller = Seller::where('id', $seller_id)->first();
        $email = $seller->email;
        $receiver = $seller->name;
        try {
            if (Config::get('app.env') == 'production') {
                Mail::to($email)->send(new BalanceRemindEmail($receiver));
            }
            $seller->last_balance_remind_time = \Carbon\Carbon::now();
            $seller->save();
        } catch (\Exception $e) {
            //
        }
    }

}
