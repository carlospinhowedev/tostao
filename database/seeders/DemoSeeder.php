<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\Category;
use App\Models\RecurringTransaction;
use App\Models\Rule;
use App\Models\Transaction;
use App\Models\User;
use App\Services\DefaultData;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;

/**
 * A fictional couple (Ana and Rui) with four months of believable activity, so
 * the dashboard has something to show on a fresh install and in screenshots.
 *
 *   php artisan db:seed --class=DemoSeeder
 *
 * Log in as ana@example.com / password. Everything here is invented.
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        mt_srand(2027); // deterministic: the same demo every time

        $ana = User::create(['name' => 'Ana Silva', 'email' => 'ana@example.com', 'password' => Hash::make('password'), 'color' => '#c2548a']);
        $rui = User::create(['name' => 'Rui Costa', 'email' => 'rui@example.com', 'password' => Hash::make('password'), 'color' => '#2f6fb5']);

        DefaultData::install();
        $cat = fn (string $name) => Category::where('name', __($name))->value('id');

        $joint = Account::first();
        $joint->update(['name' => __('Joint account'), 'opening_balance_cents' => 180000]);
        $anaAcc = Account::create(['name' => 'Conta Ana', 'type' => 'bank', 'owner_id' => $ana->id, 'opening_balance_cents' => 62000]);
        $ruiAcc = Account::create(['name' => 'Conta Rui', 'type' => 'bank', 'owner_id' => $rui->id, 'opening_balance_cents' => 41000]);
        Account::create(['name' => __('Cash'), 'type' => 'cash', 'opening_balance_cents' => 8000]);

        // Budgets
        foreach (['Groceries' => 40000, 'Eating out' => 15000, 'Fuel and transport' => 18000, 'Leisure' => 10000] as $name => $cents) {
            Category::where('name', __($name))->update(['monthly_budget_cents' => $cents]);
        }

        // Rules that make a fresh import feel smart
        foreach ([
            ['PINGO DOCE', 'Groceries'], ['CONTINENTE', 'Groceries'], ['LIDL', 'Groceries'],
            ['GALP', 'Fuel and transport'], ['REPSOL', 'Fuel and transport'],
            ['EDP', 'Home and bills'], ['MEO', 'Home and bills'], ['AGUAS', 'Home and bills'],
            ['FARMACIA', 'Health'], ['CAFE', 'Eating out'], ['RESTAURANTE', 'Eating out'],
        ] as $i => [$pattern, $category]) {
            Rule::create(['pattern' => $pattern, 'category_id' => $cat($category), 'priority' => 100 + $i]);
        }
        Rule::create(['pattern' => 'NETFLIX', 'category_id' => $cat('Leisure'), 'set_owner' => true, 'owner_id' => $rui->id, 'priority' => 50]);

        $make = function (Account $account, string $date, string $description, int $cents, ?string $category, ?User $owner, ?User $paidBy = null) use ($cat) {
            Transaction::create([
                'account_id' => $account->id,
                'category_id' => $category ? $cat($category) : null,
                'owner_id' => $owner?->id,
                'paid_by_id' => $owner === null ? $paidBy?->id : null,
                'date' => $date,
                'description' => $description,
                'amount_cents' => $cents,
            ]);
        };
        $pick = fn (array $list) => $list[mt_rand(0, count($list) - 1)];
        $money = fn (int $min, int $max) => intdiv(mt_rand($min, $max), 5) * 5; // cents, in steps of 0,05 €

        $start = Carbon::now()->startOfMonth()->subMonths(3);
        for ($m = 0; $m < 4; $m++) {
            $month = $start->copy()->addMonths($m);
            $day = fn (int $d) => $month->copy()->day(min($d, $month->daysInMonth))->format('Y-m-d');
            $last = $month->isSameMonth(now()) ? now()->day : $month->daysInMonth;
            $rand = fn (int $from, int $to) => $day(mt_rand($from, max($from, min($to, $last))));

            // Income and fixed costs
            $make($anaAcc, $day(28), 'Vencimento Ana', 145000, 'Salary', $ana);
            $make($ruiAcc, $day(27), 'Vencimento Rui', 132000, 'Salary', $rui);
            $make($joint, $day(2), 'Prestação da casa', -55000, 'Mortgage and rent', null);
            $make($joint, $day(5), 'Seguro multirriscos', -2150, 'Insurance', null);
            $make($joint, $day(8), 'EDP eletricidade', -$money(2400, 3400), 'Home and bills', null);
            $make($joint, $day(9), 'MEO internet e TV', -4990, 'Home and bills', null);
            $make($joint, $day(10), 'Águas do município', -$money(1200, 1900), 'Home and bills', null);
            $make($ruiAcc, $day(15), 'Netflix', -1299, 'Leisure', $rui);
            // Each of them tops up the joint account every month (transfers never count as spending).
            $make($anaAcc, $day(1), 'Transferência para conta conjunta', -110000, 'Transfers', $ana);
            $make($ruiAcc, $day(1), 'Transferência para conta conjunta', -90000, 'Transfers', $rui);
            $make($joint, $day(1), 'Contribuição Ana', 110000, 'Transfers', null);
            $make($joint, $day(1), 'Contribuição Rui', 90000, 'Transfers', null);
            $make($joint, $day(3), 'Transferência para conta de poupança', -20000, 'Transfers', null);

            // Groceries: shared, paid by whoever was at the shop
            foreach (range(1, 8) as $i) {
                $payer = $i % 3 === 0 ? $ana : $rui;
                $acc = $payer === $ana ? $anaAcc : $ruiAcc;
                $make($i % 4 === 0 ? $joint : $acc, $rand(1, 31), $pick(['Pingo Doce', 'Continente', 'Lidl', 'Mercado local']), -$money(1100, 6800), 'Groceries', null, $i % 4 === 0 ? null : $payer);
            }

            // Personal life
            foreach ([[$ana, $anaAcc], [$rui, $ruiAcc]] as [$person, $acc]) {
                foreach (range(1, 2) as $_) {
                    $make($acc, $rand(1, 31), $pick(['Galp', 'Repsol', 'Prio']), -$money(2400, 5200), 'Fuel and transport', $person);
                }
                foreach (range(1, 3) as $_) {
                    $make($acc, $rand(1, 31), $pick(['Café Central', 'Restaurante O Cantinho', 'Pastelaria Aliança', 'Tasca do Zé']), -$money(180, 2600), 'Eating out', $person);
                }
                $make($acc, $rand(1, 31), $pick(['Cinema', 'Livraria', 'Ginásio', 'Concerto']), -$money(900, 3200), 'Leisure', $person);
                if (mt_rand(0, 1)) {
                    $make($acc, $rand(1, 31), 'Farmácia Lima', -$money(400, 2400), 'Health', $person);
                }
                if (mt_rand(0, 2) === 0) {
                    $make($acc, $rand(1, 31), $pick(['Zara', 'Worten', 'Decathlon']), -$money(1500, 7000), 'Shopping', $person);
                }
            }
            $make($joint, $rand(10, 28), $pick(['Jantar com amigos', 'Restaurante Mar Alto']), -$money(2800, 5500), 'Eating out', null, $pick([$ana, $rui]));
            $make($joint, $day(4), 'Comissão da conta', -800, 'Bank fees', null);
        }

        // Recurring: the things that will keep appearing by themselves
        $next = Carbon::now()->startOfMonth()->addMonth();
        foreach ([
            [$joint, 'Prestação da casa', -55000, 'Mortgage and rent', null, 2],
            [$joint, 'MEO internet e TV', -4990, 'Home and bills', null, 9],
            [$anaAcc, 'Vencimento Ana', 145000, 'Salary', $ana, 28],
            [$ruiAcc, 'Vencimento Rui', 132000, 'Salary', $rui, 27],
            [$ruiAcc, 'Netflix', -1299, 'Leisure', $rui, 15],
        ] as [$acc, $description, $cents, $category, $owner, $d]) {
            RecurringTransaction::create([
                'description' => $description, 'amount_cents' => $cents, 'account_id' => $acc->id,
                'category_id' => $cat($category), 'owner_id' => $owner?->id, 'frequency' => 'monthly',
                'next_date' => $next->copy()->day($d),
            ]);
        }
    }
}
