<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Seeder;

class UsersSeeder extends Seeder
{
    public function run(): void
    {
        $teamId = Team::query()->value('id');

        $users = [
            ['name' => 'Admin', 'email' => 'admin@levelead.com.br', 'role' => UserRole::Admin, 'team_id' => null],
            ['name' => 'Gestor Comercial', 'email' => 'manager@levelead.com.br', 'role' => UserRole::Manager, 'team_id' => $teamId],
            ['name' => 'Consultor Comercial', 'email' => 'consultant@levelead.com.br', 'role' => UserRole::Consultant, 'team_id' => $teamId],
        ];

        foreach ($users as $user) {
            User::query()->updateOrCreate(
                ['email' => $user['email']],
                [
                    'name' => $user['name'],
                    'role' => $user['role'],
                    'team_id' => $user['team_id'],
                    'password' => 'password',
                    'email_verified_at' => now(),
                ],
            );
        }
    }
}
