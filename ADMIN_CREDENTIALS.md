# Admin Credentials

## Default Admin User

After running the seeders, the system creates a default admin user with the following credentials:

- **Email**: admin@ghanem-lvju-egypt.com
- **Username**: admin
- **Password**: 0150386787
- **Phone**: 0150386787

## Important Notes

1. The password is properly hashed using `Hash::make()` in the seeder
2. If you're experiencing "The password you entered is incorrect" error, please ensure you're using the exact password: `0150386787`
3. The user is assigned the "Super Admin" role with full system access
4. After first login, it's recommended to change the default password for security reasons

## Seeder Location

The admin user is created in: `database/seeders/UsersSeeder.php`

## Troubleshooting

If you cannot login:
1. Verify the email is: admin@ghanem-lvju-egypt.com
2. Verify the password is: 0150386787 (no spaces)
3. Check if the user is marked as active (`is_active = true`)
4. Ensure the database seeders have been run: `php artisan db:seed`
