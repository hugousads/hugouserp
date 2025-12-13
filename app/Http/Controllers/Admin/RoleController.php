<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    public function index(Request $request)
    {
        $per = min(max($request->integer('per_page', 50), 1), 200);
        $q = Role::query()->where('guard_name', 'web')->orderBy('name');
        if ($s = $request->input('q')) {
            $q->where('name', 'like', '%'.$s.'%');
        }

        return $this->ok($q->paginate($per));
    }

    public function store(Request $request)
    {
        $this->validate($request, [
            'name' => [
                'required',
                'string',
                'max:190',
                'unique:roles,name,NULL,id,guard_name,web',
            ],
        ]);

        $role = Role::create([
            'name' => $request->input('name'),
            'guard_name' => 'web',
        ]);

        return $this->ok($role->toArray(), __('Created'), 201);
    }

    public function update(Request $request, int $id)
    {
        $role = Role::where('guard_name', 'web')->findOrFail($id);
        
        $this->validate($request, [
            'name' => [
                'required',
                'string',
                'max:190',
                'unique:roles,name,'.$id.',id,guard_name,web',
            ],
        ]);

        $role->update(['name' => $request->input('name')]);

        return $this->ok($role->toArray(), __('Updated'));
    }

    public function destroy(int $id)
    {
        Role::where('guard_name', 'web')->whereKey($id)->delete();

        return $this->ok([], __('Deleted'));
    }
}
