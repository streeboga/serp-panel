<?php

declare(strict_types=1);

use App\Models\Category;
use App\Models\Cluster;
use App\Models\Domain;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Region;
use App\Models\User;

function createUserWithOrg(string $role = 'admin'): array
{
    $user = User::factory()->create();
    $org = Organization::create(['name' => 'Test Org', 'slug' => 'test-org-'.$user->id]);
    $org->users()->attach($user->id, ['role' => $role]);

    return compact('user', 'org');
}

function createFullStack(string $role = 'admin'): array
{
    $h = createUserWithOrg($role);
    $project = Project::create(['organization_id' => $h['org']->id, 'name' => 'Test Project']);
    $domain = Domain::create(['project_id' => $project->id, 'name' => 'test.com', 'is_own' => true]);
    $category = Category::create(['domain_id' => $domain->id, 'name' => 'Main']);
    $cluster = Cluster::create(['category_id' => $category->id, 'name' => 'Cluster 1']);
    $region = Region::first() ?? Region::create([
        'engine' => 'google',
        'code' => 'US',
        'name' => 'United States',
        'google_gl' => 'us',
        'google_hl' => 'en',
    ]);

    return array_merge($h, compact('project', 'domain', 'category', 'cluster', 'region'));
}

function orgHeaders(Organization $org): array
{
    return ['X-Organization-Id' => (string) $org->id];
}

/**
 * Зовёт handle() джобы через контейнер: зависимости резолвятся сами, и добавление
 * новой не ломает каждый тест, который эту джобу дёргает.
 */
function runJob(object $job): void
{
    app()->call([$job, 'handle']);
}
