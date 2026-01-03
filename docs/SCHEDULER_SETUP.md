# Scheduler Setup Guide

This guide explains how to configure the Laravel task scheduler for the ERP system in different deployment environments.

## Why Is This Important?

The ERP system relies on scheduled tasks for critical features:
- **Scheduled Reports**: Automatically generate and email reports
- **POS Day Closing**: Close the POS day at end of business
- **Payroll Processing**: Monthly payroll calculations
- **Stock Alerts**: Daily low stock notifications
- **Backups**: Automated system backups
- **Rental Invoices**: Generate recurring rental invoices

Without proper scheduler configuration, these features **will not work**.

---

## Quick Start

Run the following command to get your exact cron configuration:

```bash
php artisan erp:scheduler:install
```

This will display the cron line specific to your installation.

---

## Option A: cPanel Setup (Shared Hosting)

### Step 1: Access cPanel
1. Log in to your cPanel dashboard
2. Navigate to **"Cron Jobs"** (under "Advanced" section)

### Step 2: Add Cron Job
1. Under **"Add New Cron Job"**, set:
   - **Common Settings**: `Once Per Minute (* * * * *)`
   
2. In the **Command** field, enter:
   ```bash
   cd /home/YOUR_USERNAME/public_html && /usr/local/bin/php artisan schedule:run >> /dev/null 2>&1
   ```
   
   > **Note**: Replace `/home/YOUR_USERNAME/public_html` with your actual project path. You can find this by running `pwd` in your project directory via SSH.

3. Click **"Add New Cron Job"**

### Step 3: Verify
- Wait 1-2 minutes
- Check `storage/logs/laravel.log` for scheduler activity
- Or run `php artisan schedule:list` via SSH to see scheduled tasks

---

## Option B: Linux Server (VPS/Dedicated)

### Using Crontab (Recommended)

1. Open crontab for editing:
   ```bash
   crontab -e
   ```

2. Add this line at the end:
   ```bash
   * * * * * cd /var/www/erp && php artisan schedule:run >> /dev/null 2>&1
   ```
   
   > **Note**: Replace `/var/www/erp` with your actual project path.

3. Save and exit (in nano: `Ctrl+X`, then `Y`, then `Enter`)

4. Verify the cron is registered:
   ```bash
   crontab -l
   ```

### Using Systemd Timer (Alternative)

For servers using systemd, you can use a timer instead of cron.

1. Create the service file `/etc/systemd/system/erp-scheduler.service`:
   ```ini
   [Unit]
   Description=ERP Laravel Scheduler
   After=network.target
   
   [Service]
   Type=oneshot
   User=www-data
   WorkingDirectory=/var/www/erp
   ExecStart=/usr/bin/php artisan schedule:run
   
   [Install]
   WantedBy=multi-user.target
   ```

2. Create the timer file `/etc/systemd/system/erp-scheduler.timer`:
   ```ini
   [Unit]
   Description=Run ERP Laravel Scheduler every minute
   
   [Timer]
   OnCalendar=*:*:00
   Persistent=true
   
   [Install]
   WantedBy=timers.target
   ```

3. Enable and start:
   ```bash
   sudo systemctl daemon-reload
   sudo systemctl enable erp-scheduler.timer
   sudo systemctl start erp-scheduler.timer
   ```

4. Verify:
   ```bash
   sudo systemctl status erp-scheduler.timer
   ```

---

## Option C: Docker Deployment

If running in Docker, add this to your `docker-compose.yml`:

```yaml
services:
  scheduler:
    build: .
    command: sh -c "while true; do php artisan schedule:run --verbose --no-interaction; sleep 60; done"
    depends_on:
      - app
    volumes:
      - .:/var/www/html
```

Or use a dedicated scheduler container with cron:

```dockerfile
FROM php:8.2-cli
# ... your PHP setup ...
RUN apt-get update && apt-get install -y cron
COPY scheduler-cron /etc/cron.d/scheduler-cron
RUN chmod 0644 /etc/cron.d/scheduler-cron
RUN crontab /etc/cron.d/scheduler-cron
CMD ["cron", "-f"]
```

---

## Scheduled Tasks Reference

| Task | Schedule | Description |
|------|----------|-------------|
| `reports:run-scheduled` | Hourly | Run scheduled reports and send via email |
| `pos:close-day` | Daily 23:55 | Close POS day for all branches |
| `rental:generate-recurring` | Daily 00:30 | Generate recurring rental invoices |
| `system:backup` | Daily 02:00 | Run verified system backup |
| `hrm:payroll` | Monthly 1st 01:30 | Run monthly payroll |
| `stock:check-low` | Daily 07:00 | Check for low stock alerts |

---

## Troubleshooting

### Task Not Running?

1. **Check cron is active**:
   ```bash
   service cron status
   # or
   systemctl status cron
   ```

2. **Check Laravel logs**:
   ```bash
   tail -f storage/logs/laravel.log
   ```

3. **Run scheduler manually**:
   ```bash
   php artisan schedule:run
   ```

4. **List scheduled tasks**:
   ```bash
   php artisan schedule:list
   ```

### Permission Issues?

Ensure the web server user (e.g., `www-data`) has write access:
```bash
sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache
```

### Scheduled Reports Not Sending?

1. Check mail configuration in `.env`
2. Test email: `php artisan tinker` then `Mail::raw('Test', fn($m) => $m->to('test@example.com'));`
3. Check `storage/logs/laravel.log` for mail errors

---

## Need Help?

Run the installation helper for environment-specific instructions:
```bash
php artisan erp:scheduler:install
```

For systemd configuration details:
```bash
php artisan erp:scheduler:install --show-systemd
```
