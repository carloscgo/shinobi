<?php

namespace CarlosCGO\Shinobi\Traits;

trait ShinobiTrait
{
    use PermissionTrait;

    /**
     * The shinobi cache tag used by the user model.
     *
     * @return string
     */
    public static function getShinobiTag()
    {
        return 'shinobi.users';
    }

    /*
    |----------------------------------------------------------------------
    | Role Trait Methods
    |----------------------------------------------------------------------
    |
    */

    /**
     * Users can have many roles.
     *
     * @return Illuminate\Database\Eloquent\Model
     */
    public function roles()
    {
        return $this->belongsToMany('\CarlosCGO\Shinobi\Models\Role')->withTimestamps();
    }

    /**
     * Get all user roles.
     *
     * @return array|null
     */
    public function getRoles()
    {
        if (!is_null($this->roles())) {
            return $this->roles()->pluck('slug')->all();
        }
    }

    /**
     * Checks if the user has the given role.
     *
     * @param string $slug
     *
     * @return bool
     */
    public function isRole($slug)
    {
        $slug = strtolower($slug);

        $roles = $this->RoleUsers()->with('Roles')->get();

        if ($roles[0]->Roles->slug == $slug) {
            return true;
        }

        return false;
    }

    /**
     * Assigns the given role to the user.
     *
     * @param int $roleId
     *
     * @return bool
     */
    public function assignRole($roleId = null)
    {
        $this->flushPermissionCache();

        if (!is_numeric($roleId)) {
            $roleId = \CarlosCGO\Shinobi\Models\Role::where('slug', $roleId)->pluck('id')->first();
        }

        $roles = $this->roles()->get();

        if (!$roles->contains($roleId)) {
            return $this->roles()->attach($roleId);
        }

        return false;
    }

    /**
     * Revokes the given role from the user.
     *
     * @param int $roleId
     *
     * @return bool
     */
    public function revokeRole($roleId = '')
    {
        $this->flushPermissionCache();

        if (!is_numeric($roleId)) {
            $roleId = \CarlosCGO\Shinobi\Models\Role::where('slug', $roleId)->pluck('id')->first();
        }

        return $this->roles()->detach($roleId);
    }

    /**
     * Syncs the given role(s) with the user.
     *
     * @param array $roleIds
     *
     * @return bool
     */
    public function syncRoles(array $roleIds)
    {
        $this->flushPermissionCache();

        return $this->roles()->sync($roleIds);
    }

    /**
     * Revokes all roles from the user.
     *
     * @return bool
     */
    public function revokeAllRoles()
    {
        $this->flushPermissionCache();

        return $this->roles()->detach();
    }

    /*
    |----------------------------------------------------------------------
    | Permission Trait Methods
    |----------------------------------------------------------------------
    |
    */

    /**
     * Get permission slugs assigned to user.
     *
     * @return array
     */
    public function getUserPermissions()
    {
        return $this->permissions()->pluck('slug')->all();
    }

    /**
     * Get all user role permissions fresh from database
     *
     * @return array|null
     */
    protected function getFreshPermissions()
    {
        $permissions = [[], $this->getUserPermissions()];

        $roles = $this->RoleUsers()->with(['Roles' => function ($query) {
            return $query->with(['PermissionRoles' => function ($query) {
                return $query->with('Permissions')->get();
            }])->get();
        }])->get();

        foreach ($roles[0]->Roles->PermissionRoles as $item) {
            $per[] = $item->Permissions->slug;
        }

        return call_user_func_array('array_merge', [[], $per]);
    }

    /**
     * Check if user has the given permission.
     *
     * @param string $permission
     * @param array  $arguments
     *
     * @return bool
     */
    public function can($permission, $arguments = [])
    {
        foreach ($this->roles()->get() as $role) {
            if ($role->special === 'no-access') {
                return false;
            }

            if ($role->special === 'all-access') {
                return true;
            }
        }

        return $this->hasAllPermissions($permission, $this->getPermissions());
    }

    /**
     * Check if user has at least one of the given permissions.
     *
     * @param array $permissions
     *
     * @return bool
     */
    public function canAtLeast(array $permissions)
    {
        foreach ($this->roles()->get() as $role) {
            if ($role->special === 'no-access') {
                return false;
            }

            if ($role->special === 'all-access') {
                return true;
            }

            if ($role->canAtLeast($permissions)) {
                return true;
            }
        }

        return false;
    }

    /*
    |----------------------------------------------------------------------
    | Magic Methods
    |----------------------------------------------------------------------
    |
    */

    /**
     * Magic __call method to handle dynamic methods.
     *
     * @param string $method
     * @param array  $arguments
     *
     * @return mixed
     */
    public function __call($method, $arguments = [])
    {
        // Handle isRoleslug() methods
        if (starts_with($method, 'is') and $method !== 'is') {
            $role = kebab_case(substr($method, 2));

            return $this->isRole($role);
        }

        // Handle canDoSomething() methods
        if (starts_with($method, 'can') and $method !== 'can') {
            $permission = kebab_case(substr($method, 3));

            return $this->can($permission);
        }

        return parent::__call($method, $arguments);
    }
}
