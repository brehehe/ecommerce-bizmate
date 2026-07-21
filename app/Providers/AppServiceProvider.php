<?php

namespace App\Providers;

use App\Models\RefundRequest;
use App\Models\ReturnRequest;
use App\Models\Transaction;
use App\Observers\RefundRequestObserver;
use App\Observers\ReturnRequestObserver;
use App\Observers\TransactionObserver;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Events\Login;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Expression;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use URL;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Model::preventLazyLoading(! app()->isProduction());

        if (app()->environment('production')) {
            URL::forceScheme('https');
        }

        $this->configureDefaults();

        // Register observers
        Transaction::observe(TransactionObserver::class);
        RefundRequest::observe(RefundRequestObserver::class);
        ReturnRequest::observe(ReturnRequestObserver::class);

        Event::listen(function (Login $event) {
            $event->user->update([
                'last_active_at' => now(),
            ]);
        });

        Route::bind('transaction', function (string $value) {
            if (Str::isUuid($value) || is_numeric($value)) {
                return Transaction::findOrFail($value);
            }

            return Transaction::where('transaction_number', $value)->firstOrFail();
        });

        // Register a global booted event listener for all Eloquent models to apply a default ASC ordering
        Event::listen('eloquent.booted: *', function ($eventName, array $data) {
            $model = $data[0];
            $modelClass = get_class($model);

            if (str_starts_with($modelClass, 'App\\')) {
                $modelClass::addGlobalScope('default_order', function (Builder $builder) {
                    $query = $builder->getQuery();
                    if (empty($query->orders) && empty($query->groups) && empty($query->aggregate)) {
                        // Check if any custom selected columns contain aggregate functions
                        $hasAggregate = false;
                        if (! empty($query->columns)) {
                            foreach ($query->columns as $column) {
                                $colStr = $column instanceof Expression
                                    ? $column->getValue(DB::connection()->getQueryGrammar())
                                    : (string) $column;

                                if (preg_match('/\b(count|sum|avg|min|max|stddev|variance|string_agg|array_agg|json_agg|jsonb_agg)\s*\(/i', $colStr)) {
                                    $hasAggregate = true;
                                    break;
                                }
                            }
                        }

                        if (! $hasAggregate) {
                            $model = $builder->getModel();
                            if ($model->getKeyName()) {
                                $builder->orderBy($model->getTable().'.'.$model->getKeyName(), 'asc');
                            }
                        }
                    }
                });
            }
        });
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(
            fn (): ?Password => app()->isProduction()
                ? Password::min(12)
                    ->mixedCase()
                    ->letters()
                    ->numbers()
                    ->symbols()
                    ->uncompromised()
                : null,
        );
    }
}
