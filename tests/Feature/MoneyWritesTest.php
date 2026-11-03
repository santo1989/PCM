<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\HandCash;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/** Routes that write money or change who can do what. Runs only on the *_test database (see CreatesApplication). */
class MoneyWritesTest extends TestCase
{
    use RefreshDatabase;

    private function user(int $roleId): User
    {
        return User::factory()->create(['role_id' => $roleId, 'mobile' => uniqid(), 'dob' => '1990-01-01']);
    }

    public function test_transfer_saves_two_linked_rows()
    {
        $this->actingAs($this->user(1))->post('/handCashes_transfer', [
            'date' => '2026-01-15', 'amount' => '500', 'name' => 'move',
            'types1' => 'WIDROWS', 'rules1' => 'CASH', 'types2' => 'SAVE', 'rules2' => 'DPS',
        ])->assertRedirect();

        [$out, $in] = HandCash::orderBy('id')->get()->all();
        $this->assertSame([$in->id, $out->id], [$out->transfer_pair_id, $in->transfer_pair_id]);
        $this->assertSame(['WIDROWS', 'SAVE'], [$out->types, $in->types]);
    }

    public function test_transfer_with_bad_amount_writes_nothing()
    {
        $this->actingAs($this->user(1))->post('/handCashes_transfer', [
            'date' => '2026-01-15', 'amount' => 'abc',
            'types1' => 'WIDROWS', 'rules1' => 'CASH', 'types2' => 'SAVE', 'rules2' => 'DPS',
        ])->assertSessionHasErrors('amount');

        $this->assertSame(0, HandCash::count());
    }

    public function test_set_as_budgets_only_changes_expense_categories()
    {
        $food = Category::create(['name' => 'FOOD', 'types' => 'EXPENSE', 'rules' => 'NEEDS', 'monthly_budget' => 100]);
        $shop = Category::create(['name' => 'SHOPPING', 'types' => 'EXPENSE', 'rules' => 'WANTS', 'monthly_budget' => 300]);
        $salary = Category::create(['name' => 'SALARY', 'types' => 'INCOME', 'rules' => '0']);

        $this->actingAs($this->user(1))->post('/categories/budgets', [
            'budgets' => ['FOOD' => '12000', 'SHOPPING' => '', 'SALARY' => '999'],
        ])->assertRedirect(route('categories.index'));

        $this->assertEquals(12000, $food->fresh()->monthly_budget);
        $this->assertNull($shop->fresh()->monthly_budget);
        $this->assertNull($salary->fresh()->monthly_budget);
    }

    public function test_non_admin_cannot_change_roles_or_add_users()
    {
        $user = $this->user(2);

        $this->actingAs($user)->put("/users/{$user->id}", ['role_id' => 1])->assertForbidden();
        $this->actingAs($user)->get('/register')->assertForbidden();
        $this->assertSame(2, (int) $user->fresh()->role_id);
    }

    public function test_profile_picture_must_be_an_image()
    {
        $admin = $this->user(1);

        $this->actingAs($admin)->put("/users/{$admin->id}", [
            'name' => 'x', 'picture' => UploadedFile::fake()->create('shell.php', 1, 'text/x-php'),
        ])->assertSessionHasErrors('picture');
    }
}
