<?php

namespace Database\Seeders;

use App\Models\Client;
use App\Models\LedgerEntry;
use App\Models\Tag;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class DevDammyDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        dump(config('dev-plug'));

        if (config('dev-plug.is_on_loop') && !config('dev-plug.to_force_action')) {
            $this->command->info('On loop. Returning on ' . __METHOD__);

            return;
        }

        if (app()->isProduction()) {
            return;
        }

        $this->dummyUsers();
        $this->dummyTags();
        $this->dummyClientsAndWallets();
        $this->dummyLedgerEntries();
    }

    protected function dummyUsers()
    {
        $users = [
            [
                'name' => 'Customer1',
                'email' => 'customer1@mail.com',
                'password' => 'power@123',
                'email_verified_at' => now(),
                'role' => 'customer',
            ],
        ];

        foreach ($users as $userData) {
            if (!isset($userData['email'])) {
                continue;
            }

            $role = $userData['role'] ?? null;
            $password = $userData['password'] ?? 'power@123';
            $userData = \Arr::except($userData, ['role']);
            $userData['password'] = Hash::make($password);

            $user = User::updateOrCreate(
                [
                    'email' => $userData['email']
                ],
                $userData,
            );

            $this->command->newLine();
            $this->command->info("Email: {$user?->email}");
            $this->command->info("Password: {$password}");

            if (!$role || !is_string($role) || !class_exists(Role::class)) {
                continue;
            }

            $userRole = Role::firstWhere('name', $role);

            if (!$userRole || $user->hasRole('super_admin')) {
                continue;
            }

            $user->syncRoles($userRole);

            $this->command->info("Role: {$userRole?->name}");
        }
    }

    protected function dummyTags(): void
    {
        $this->command->newLine();
        $this->command->info('Creating tags...');

        $tags = [
            'Desenvolvimento',
            'Bug Fix',
            'Reunião',
            'Code Review',
            'Documentação',
            'Deploy',
            'Suporte',
        ];

        foreach ($tags as $tagName) {
            Tag::firstOrCreate(['name' => $tagName]);
        }

        $this->command->info('Tags created: ' . count($tags));
    }

    protected function dummyClientsAndWallets(): void
    {
        $this->command->newLine();
        $this->command->info('Creating clients and wallets...');

        $clientsData = [
            [
                'name' => 'Cliente 1',
                'notes' => 'Cliente de desenvolvimento de software',
                'wallets' => [
                    [
                        'name' => 'Projeto 1',
                        'description' => 'Sistema de gestão interno',
                        'hourly_rate_reference' => '150.00',
                    ],
                    [
                        'name' => 'Projeto 2',
                        'description' => 'Aplicativo mobile',
                        'hourly_rate_reference' => '180.00',
                    ],
                ],
            ],
            [
                'name' => 'Cliente 2',
                'notes' => 'Cliente de consultoria',
                'wallets' => [
                    [
                        'name' => 'Projeto 3',
                        'description' => 'Website institucional',
                        'hourly_rate_reference' => '120.00',
                    ],
                    [
                        'name' => 'Projeto 4',
                        'description' => 'Sistema de vendas',
                        'hourly_rate_reference' => '160.00',
                    ],
                ],
            ],
        ];

        foreach ($clientsData as $clientData) {
            $walletsData = $clientData['wallets'];
            unset($clientData['wallets']);

            $client = Client::updateOrCreate(
                ['name' => $clientData['name']],
                $clientData
            );

            $this->command->info("Client: {$client->name}");

            foreach ($walletsData as $walletData) {
                $wallet = Wallet::updateOrCreate(
                    [
                        'client_id' => $client->id,
                        'name' => $walletData['name'],
                    ],
                    $walletData
                );

                $this->command->info("  Wallet: {$wallet->name}");
            }
        }
    }

    protected function dummyLedgerEntries(): void
    {
        $this->command->newLine();
        $this->command->info('Creating ledger entries...');

        $wallets = Wallet::all();
        $tags = Tag::all();

        if ($wallets->isEmpty()) {
            $this->command->warn('No wallets found. Skipping ledger entries.');

            return;
        }

        $entryCount = 0;

        foreach ($wallets as $wallet) {
            $startDate = now()->subMonths(3);

            for ($i = 0; $i < 15; $i++) {
                $isCredit = $i % 4 === 0;
                $hours = $isCredit ? rand(8, 40) : -rand(1, 16);
                $refDate = $startDate->copy()->addDays(rand(0, 90));

                $entry = LedgerEntry::create([
                    'wallet_id' => $wallet->id,
                    'hours' => $hours,
                    'title' => $this->generateEntryTitle($isCredit),
                    'description' => $this->generateEntryDescription($isCredit),
                    'reference_date' => $refDate->format('Y-m-d'),
                ]);

                if ($tags->isNotEmpty() && rand(0, 100) > 30) {
                    $randomTags = $tags->random(rand(1, 3));

                    $entry->tags()->attach($randomTags->pluck('id'));
                }

                $entryCount++;
            }
        }

        $this->command->info("Ledger entries created: {$entryCount}");
    }

    private function generateEntryTitle(bool $isCredit): string
    {
        if ($isCredit) {
            $titles = [
                'Pacote de horas - Janeiro',
                'Pacote de horas - Fevereiro',
                'Pacote de horas - Março',
                'Crédito adicional',
                'Horas de contingência',
                'Pacote mensal',
            ];
        } else {
            $titles = [
                'Desenvolvimento de nova feature',
                'Correção de bugs críticos',
                'Reunião de alinhamento',
                'Code review e refatoração',
                'Implementação de API',
                'Testes e validação',
                'Ajustes de layout',
                'Otimização de performance',
                'Documentação técnica',
                'Suporte técnico',
                'Deploy em produção',
                'Configuração de ambiente',
            ];
        }

        return $titles[array_rand($titles)];
    }

    private function generateEntryDescription(bool $isCredit): ?string
    {
        if ($isCredit) {
            return null;
        }

        if (rand(0, 100) < 40) {
            return null;
        }

        $descriptions = [
            'Implementação completa da funcionalidade solicitada',
            'Correção de bug reportado pelo cliente',
            'Alinhamento de requisitos e próximos passos',
            'Revisão de código e sugestões de melhorias',
            'Desenvolvimento e testes da integração',
            'Validação e correção de problemas encontrados',
            'Ajustes conforme feedback do cliente',
            'Análise e otimização de consultas ao banco',
            'Atualização da documentação do projeto',
            'Resolução de problema em produção',
            'Publicação de nova versão',
            'Configuração de servidores e dependências',
        ];

        return $descriptions[array_rand($descriptions)];
    }
}
