<?php

namespace Application\v1\Controllers;

use App\Common\Http\Controllers\AppController;
use App\Common\Models\Wallet\Wallet;
use App\Common\Models\Wallet\WalletTransaction;
use App\Common\Models\Wallet\WalletTransactionFilter;
use Illuminate\Http\JsonResponse;

class WalletController extends AppController
{
    public function setWallet(): JsonResponse
    {
        if ($post = $this->validation(Wallet::rules())) {
            $post['user_id'] = $this->getUser()->id;
            $wallet = Wallet::create($post);
            if ($error = $wallet->getError()) {
                $this->setError($error);
                return $this->badRequest()->error();
            }
            $this->setData($wallet->getFields());
            return $this->response();
        }
        return $this->error();
    }

    public function getWallet(): JsonResponse
    {
        $post['user_id'] = $this->getUser()->id;
        $wallet = Wallet::find($post);
        if ($error = $wallet->getError()) {
            $this->setError($error);
            return $this->badRequest()->error();
        }
        $this->setData($wallet->getFields());
        return $this->response();
    }

    public function getTransaction(): JsonResponse
    {
        if ($post = $this->validation(WalletTransactionFilter::rules())) {
            $post['user_id'] = $this->getUser()->id;
            if ($this->getUser()->wallet) {
                $data = WalletTransactionFilter::builder($post)->apply()->get();
                $this->setData($data);
                return $this->response();
            }
            return $this->setError(['wallet' => 'У пользователя нет кошелька'])->badRequest()->error();
        }
        return $this->error();
    }

    public function setTransaction(): JsonResponse
    {
        if ($post = $this->validation(WalletTransaction::rules())) {
            $post['user_id'] = $this->getUser()->id;
            $post['wallet'] = $this->getUser()->wallet;
            if ($post['wallet']) {
                $data = WalletTransaction::create($post);
                if ($error = $data->getError()) {
                    $this->setError($error);
                    return $this->badRequest()->error();
                }
                $this->setData($data->getFields());
                return $this->response();
            }
            return $this->setError(['wallet' => 'У пользователя нет кошелька'])->badRequest()->error();
        }
        return $this->error();
    }
}
