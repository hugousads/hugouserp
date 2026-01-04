# Store Integration Guide / دليل تكامل المتاجر

**Version:** 1.0  
**Last Updated:** 2026-01-04

---

## Overview / نظرة عامة

HugouERP supports integration with external e-commerce platforms through the Store Integration module. This allows businesses to:

- Sync products between HugouERP and online stores
- Receive orders from online platforms
- Manage inventory across channels
- Automate order fulfillment

## Supported Platforms / المنصات المدعومة

| Platform | Status | API Version |
|----------|--------|-------------|
| WooCommerce | ✅ Supported | REST API v3 |
| Shopify | ✅ Supported | Admin API 2024-01 |
| Salla | 🔄 Planned | - |
| OpenCart | 🔄 Planned | - |

---

## Setup / الإعداد

### 1. Create a Store

Navigate to **Admin → Stores** and click "Add Store".

Required fields:
- **Name**: Display name for the store
- **Platform**: Select the e-commerce platform
- **Status**: Enable/disable the integration

### 2. Configure Integration

Each platform requires specific credentials:

#### WooCommerce
```
URL: https://your-store.com
Consumer Key: ck_xxxxx
Consumer Secret: cs_xxxxx
```

#### Shopify
```
Shop URL: your-store.myshopify.com
Access Token: shpat_xxxxx
```

### 3. Set Permissions

Configure which operations are allowed:

| Permission | Description |
|------------|-------------|
| `products.sync` | Sync products to/from store |
| `orders.pull` | Import orders from store |
| `orders.push` | Push order status updates |
| `inventory.sync` | Sync stock levels |

---

## Module Integration / تكامل المديولات

Store integrations can sync products from any **Data Module**:

| Module | Item Type | Sync Fields |
|--------|-----------|-------------|
| General | Products | name, sku, price, stock |
| Motorcycle | Bikes | + brand, model, year |
| Spares | Parts | + part_number, compatibility |
| Wood | Lumber | + dimensions, type |
| Rental | Units | + rental_duration, deposit |
| Manufacturing | Materials | + material_type, BOM |

### Linking Products to Stores

1. Go to product edit page
2. Click "Store Channels" tab
3. Select stores to publish to
4. Map product fields to store fields

---

## API Endpoints / نقاط API

### Webhook Endpoints

```
POST /api/v1/stores/{store}/webhooks/orders
POST /api/v1/stores/{store}/webhooks/products
POST /api/v1/stores/{store}/webhooks/inventory
```

### Sync Endpoints

```
POST /api/v1/stores/{store}/sync/products
POST /api/v1/stores/{store}/sync/inventory
GET  /api/v1/stores/{store}/orders
```

---

## Events / الأحداث

The following events are dispatched during store operations:

| Event | Triggered When |
|-------|----------------|
| `ProductSyncedToStore` | Product pushed to external store |
| `OrderReceivedFromStore` | New order received via webhook |
| `InventorySyncCompleted` | Stock sync finished |
| `StoreConnectionFailed` | API connection error |

---

## Troubleshooting / استكشاف الأخطاء

### Common Issues

1. **Invalid API Credentials**
   - Verify credentials in store settings
   - Check API permissions on the e-commerce platform

2. **Sync Failures**
   - Check webhook logs in Admin → Audit Logs
   - Verify product SKUs match between systems

3. **Order Import Issues**
   - Ensure customer mapping is configured
   - Check payment method mappings

### Logs

View integration logs at:
- **Admin → Audit Logs** (filter by module: stores)
- **Storage → logs/store-sync.log**

---

## Security / الأمان

- All API keys are encrypted at rest
- Webhook secrets verify request authenticity
- SSL/TLS required for all connections
- Rate limiting: 60 requests/minute per store

---

## Permissions / الصلاحيات

| Permission | Description |
|------------|-------------|
| `stores.view` | View store configurations |
| `stores.create` | Create new store connections |
| `stores.edit` | Edit store settings |
| `stores.delete` | Delete store connections |
| `stores.sync` | Trigger manual syncs |

---

## Best Practices / أفضل الممارسات

1. **Start with Test Mode**
   - Use sandbox/test credentials first
   - Verify sync before going live

2. **Map SKUs Consistently**
   - Use consistent SKU format across all channels
   - ERP SKU should be the "master" identifier

3. **Monitor Sync Status**
   - Set up notifications for sync failures
   - Review logs weekly

4. **Handle Stock Carefully**
   - Configure safety stock levels
   - Don't oversell inventory

---

**Document Maintained By:** Development Team  
**Related Documents:**
- `ARCHITECTURE.md` - System architecture
- `docs/archive/MODULE_PRODUCT_INTEGRATION.md` - Product module details
