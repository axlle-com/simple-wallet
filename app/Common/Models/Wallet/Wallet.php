<?php

namespace App\Common\Models\Wallet;

use App\Common\Models\BaseModel;
use App\Common\Models\User\User;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

/**
 * This is the model class for table "{{%wallet}}".
 *
 * @property int $id
 * @property int $user_id
 * @property int $wallet_currency_id
 * @property float $balance
 * @property int|null $created_at
 * @property int|null $updated_at
 * @property int|null $deleted_at
 *
 * @property string $reason
 * @property string $type
 *
 * @property string $wallet_currency_name
 * @property string $wallet_currency_title
 * @property string $wallet_currency_is_national
 *
 * @property User $user
 * @property WalletCurrency $walletCurrency
 * @property WalletTransaction[] $walletTransactions
 */
class Wallet extends BaseModel
{
    private ?WalletCurrency $_walletCurrency = null;
    protected $table = 'ax_wallet';
    protected $dateFormat = 'U';
    protected $casts = [
        'created_at' => 'timestamp',
        'updated_at' => 'timestamp',
        'deleted_at' => 'timestamp',
    ];

    public static function rules(string $type = 'set'): array
    {
        return [
                'set' => [
                    'currency' => 'required|string|' . WalletCurrency::getCurrencyNameRule(),
                    'deposit' => 'required|numeric',
                ],
            ][$type] ?? [];
    }

    public function attributeLabels(): array
    {
        return [
            'id' => 'ID',
            'user_id' => 'User ID',
            'wallet_currency_id' => 'Currency ID',
            'balance' => 'Balance',
            'created_at' => 'Created At',
            'updated_at' => 'Updated At',
            'deleted_at' => 'Deleted At',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function walletCurrency(): BelongsTo
    {
        return $this->belongsTo(WalletCurrency::class, 'wallet_currency_id', 'id');
    }

    public function walletTransactions(): HasMany
    {
        return $this->hasMany(WalletTransaction::class, 'wallet_id', 'id');
    }

    public function getFields(): array
    {
        return [
            'id' => $this->id,
            'user' => $this->user->getFields(),
            'currency' => $this->walletCurrency->title, # TODO: ???????????????????????? ?????????????????? ???????? line->109
            'balance' => $this->balance,
        ];
    }

    public function getBalance(): float
    {
        return round($this->balance, 2);
    }

    public function setBalance(array $data): void
    {
        $this->balance = 0.0;
    }

    public function setCurrency(array $data): void
    {
        $walletCurrency = WalletCurrency::getCurrencyByName($data['currency']);
        if (!$walletCurrency) {
            $this->setError(['wallet_currency_id' => 'Not found']);
        } else {
            # ???????????????? ????????????
            $this->_walletCurrency = $walletCurrency;
            $this->wallet_currency_id = $walletCurrency->id;
        }
    }

    # ???????????????? ????????????
    public function setWalletCurrency(): void
    {
        $this->wallet_currency_name = $this->_walletCurrency->name;
        $this->wallet_currency_title = $this->_walletCurrency->title;
        $this->wallet_currency_is_national = $this->_walletCurrency->is_national;
    }

    public function setUser(array $data): void
    {
        if ($data['user_id']) {
            $this->user_id = $data['user_id'];
        } else {
            $this->setError(['user_id' => 'Not found']);
        }
    }

    public static function create(array $data): Wallet
    {
        if (self::query()->where('user_id', $data['user_id'])->first()) {
            return self::sendError(['user_id' => '?? ???????????????????????? ?????? ???????? ??????????????']);
        }
        DB::beginTransaction();
        $model = new self();
        ########### wallet_currency
        $model->setCurrency($data);
        ########### user
        $model->setUser($data);
        ########### balance
        $model->setBalance($data);
        ########### save
        try {
            $result = !$model->getError() && $model->save();
        } catch (Exception $exception) {
            $error = $exception->getMessage();
            $model->setError([$error]);
        }
        if ($result ?? null) {
            ########### transaction
            $model->setWalletCurrency();
            $data = [
                'currency' => $data['currency'],
                'reason' => 'transfer',
                'type' => 'credit',
                'value' => $data['deposit'],
                'wallet' => $model,
            ];
            $transaction = WalletTransaction::create($data);
            if ($error = $transaction->getError()) {
                DB::rollBack();
                return self::sendError($error);
            }
            DB::commit();
            return $model;
        }
        DB::rollBack();
        return $model->setError();
    }

    public static function find(array $data): Wallet
    {
        /* @var $model Wallet */
        $model = self::query()
            ->with(['user', 'walletCurrency'])
            ->where('user_id', $data['user_id'])
            ->first();
        if ($model) {
            return $model;
        }
        return self::sendError(['user_id' => '?? ???????????????????????? ?????? ????????????????']);
    }

    public static function builder(): Builder
    {
        return self::query()
            ->select([
                'ax_wallet.*',
                'wc.name as wallet_currency_name',
                'wc.title as wallet_currency_title',
                'wc.is_national as wallet_currency_is_national',
            ])
            ->join('ax_wallet_currency as wc', 'wc.id', '=', 'ax_wallet.wallet_currency_id');
    }


}
