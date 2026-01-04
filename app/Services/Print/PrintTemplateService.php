<?php

declare(strict_types=1);

namespace App\Services\Print;

use App\Models\PrintTemplate;
use App\Traits\HandlesServiceErrors;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Blade;

/**
 * Print Template Service
 * 
 * Manages customizable print templates for invoices, receipts, and other documents.
 */
class PrintTemplateService
{
    use HandlesServiceErrors;

    /**
     * Default template types
     */
    public const TEMPLATE_TYPES = [
        'invoice' => [
            'name' => 'Invoice',
            'description' => 'Sales invoice template',
            'default_view' => 'prints.invoice',
        ],
        'receipt' => [
            'name' => 'POS Receipt',
            'description' => 'Point of sale receipt',
            'default_view' => 'prints.pos-receipt',
        ],
        'quote' => [
            'name' => 'Quote/Estimate',
            'description' => 'Price quotation template',
            'default_view' => 'prints.quote',
        ],
        'purchase_order' => [
            'name' => 'Purchase Order',
            'description' => 'Purchase order template',
            'default_view' => 'prints.purchase-order',
        ],
        'delivery_note' => [
            'name' => 'Delivery Note',
            'description' => 'Delivery/packing slip template',
            'default_view' => 'prints.delivery-note',
        ],
        'rental_contract' => [
            'name' => 'Rental Contract',
            'description' => 'Property rental contract',
            'default_view' => 'prints.rental-contract',
        ],
    ];

    /**
     * Available placeholders for templates
     */
    public const PLACEHOLDERS = [
        'company' => [
            '{{company.name}}' => 'Company Name',
            '{{company.address}}' => 'Company Address',
            '{{company.phone}}' => 'Company Phone',
            '{{company.email}}' => 'Company Email',
            '{{company.tax_number}}' => 'Tax Number',
            '{{company.logo}}' => 'Company Logo URL',
        ],
        'document' => [
            '{{document.number}}' => 'Document Number',
            '{{document.date}}' => 'Document Date',
            '{{document.due_date}}' => 'Due Date',
            '{{document.status}}' => 'Status',
        ],
        'customer' => [
            '{{customer.name}}' => 'Customer Name',
            '{{customer.email}}' => 'Customer Email',
            '{{customer.phone}}' => 'Customer Phone',
            '{{customer.address}}' => 'Customer Address',
        ],
        'totals' => [
            '{{subtotal}}' => 'Subtotal Amount',
            '{{discount}}' => 'Discount Amount',
            '{{tax}}' => 'Tax Amount',
            '{{total}}' => 'Grand Total',
        ],
        'items' => [
            '{{#items}}...{{/items}}' => 'Loop through items',
            '{{item.name}}' => 'Item Name',
            '{{item.qty}}' => 'Quantity',
            '{{item.price}}' => 'Unit Price',
            '{{item.total}}' => 'Line Total',
        ],
    ];

    /**
     * Get all templates for a branch
     */
    public function getTemplates(?int $branchId = null): array
    {
        return $this->handleServiceOperation(
            callback: function () use ($branchId) {
                $templates = PrintTemplate::query()
                    ->when($branchId, fn($q) => $q->where('branch_id', $branchId)->orWhereNull('branch_id'))
                    ->orderBy('type')
                    ->orderBy('name')
                    ->get();

                // Group by type and add defaults for missing types
                $grouped = [];
                foreach (self::TEMPLATE_TYPES as $type => $config) {
                    $typeTemplates = $templates->where('type', $type)->values();
                    
                    $grouped[$type] = [
                        'config' => $config,
                        'templates' => $typeTemplates->map(fn($t) => [
                            'id' => $t->id,
                            'name' => $t->name,
                            'is_default' => $t->is_default,
                            'is_active' => $t->is_active,
                        ])->toArray(),
                        'has_custom' => $typeTemplates->isNotEmpty(),
                    ];
                }

                return $grouped;
            },
            operation: 'getTemplates',
            context: ['branch_id' => $branchId]
        );
    }

    /**
     * Get active template for a type
     */
    public function getActiveTemplate(string $type, ?int $branchId = null): ?PrintTemplate
    {
        return $this->handleServiceOperation(
            callback: function () use ($type, $branchId) {
                // First try to get branch-specific default
                if ($branchId) {
                    $template = PrintTemplate::where('type', $type)
                        ->where('branch_id', $branchId)
                        ->where('is_default', true)
                        ->where('is_active', true)
                        ->first();
                    
                    if ($template) {
                        return $template;
                    }
                }

                // Fall back to global default
                return PrintTemplate::where('type', $type)
                    ->whereNull('branch_id')
                    ->where('is_default', true)
                    ->where('is_active', true)
                    ->first();
            },
            operation: 'getActiveTemplate',
            context: ['type' => $type, 'branch_id' => $branchId]
        );
    }

    /**
     * Create a new template
     */
    public function createTemplate(array $data): PrintTemplate
    {
        return $this->handleServiceOperation(
            callback: function () use ($data) {
                // If this is set as default, unset other defaults for this type
                if ($data['is_default'] ?? false) {
                    PrintTemplate::where('type', $data['type'])
                        ->where('branch_id', $data['branch_id'] ?? null)
                        ->update(['is_default' => false]);
                }

                return PrintTemplate::create([
                    'name' => $data['name'],
                    'type' => $data['type'],
                    'branch_id' => $data['branch_id'] ?? null,
                    'content' => $data['content'],
                    'styles' => $data['styles'] ?? '',
                    'header_content' => $data['header_content'] ?? '',
                    'footer_content' => $data['footer_content'] ?? '',
                    'settings' => $data['settings'] ?? [],
                    'is_default' => $data['is_default'] ?? false,
                    'is_active' => $data['is_active'] ?? true,
                ]);
            },
            operation: 'createTemplate',
            context: ['name' => $data['name'], 'type' => $data['type']]
        );
    }

    /**
     * Update a template
     */
    public function updateTemplate(int $templateId, array $data): PrintTemplate
    {
        return $this->handleServiceOperation(
            callback: function () use ($templateId, $data) {
                $template = PrintTemplate::findOrFail($templateId);

                // If setting as default, unset other defaults
                if (($data['is_default'] ?? false) && !$template->is_default) {
                    PrintTemplate::where('type', $template->type)
                        ->where('branch_id', $template->branch_id)
                        ->where('id', '!=', $templateId)
                        ->update(['is_default' => false]);
                }

                $template->update([
                    'name' => $data['name'] ?? $template->name,
                    'content' => $data['content'] ?? $template->content,
                    'styles' => $data['styles'] ?? $template->styles,
                    'header_content' => $data['header_content'] ?? $template->header_content,
                    'footer_content' => $data['footer_content'] ?? $template->footer_content,
                    'settings' => $data['settings'] ?? $template->settings,
                    'is_default' => $data['is_default'] ?? $template->is_default,
                    'is_active' => $data['is_active'] ?? $template->is_active,
                ]);

                return $template->fresh();
            },
            operation: 'updateTemplate',
            context: ['template_id' => $templateId]
        );
    }

    /**
     * Render a template with data
     */
    public function render(string $type, array $data, ?int $branchId = null): string
    {
        return $this->handleServiceOperation(
            callback: function () use ($type, $data, $branchId) {
                $template = $this->getActiveTemplate($type, $branchId);

                if (!$template) {
                    // Fall back to default blade view
                    $viewName = self::TEMPLATE_TYPES[$type]['default_view'] ?? 'prints.generic';
                    return View::make($viewName, $data)->render();
                }

                // Process custom template
                $html = $this->processTemplate($template->content, $data);
                
                // Add styles
                $styles = $template->styles ?? '';
                
                // Add header and footer
                $header = $this->processTemplate($template->header_content ?? '', $data);
                $footer = $this->processTemplate($template->footer_content ?? '', $data);

                return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        {$styles}
    </style>
</head>
<body>
    <header>{$header}</header>
    <main>{$html}</main>
    <footer>{$footer}</footer>
</body>
</html>
HTML;
            },
            operation: 'render',
            context: ['type' => $type, 'branch_id' => $branchId]
        );
    }

    /**
     * Process template placeholders
     */
    protected function processTemplate(string $template, array $data): string
    {
        // Replace simple placeholders
        $html = preg_replace_callback('/\{\{([a-zA-Z0-9_.]+)\}\}/', function ($matches) use ($data) {
            $key = $matches[1];
            return $this->getNestedValue($data, $key) ?? '';
        }, $template);

        // Process item loops
        $html = preg_replace_callback('/\{\{#items\}\}(.*?)\{\{\/items\}\}/s', function ($matches) use ($data) {
            $itemTemplate = $matches[1];
            $items = $data['items'] ?? [];
            $output = '';
            
            foreach ($items as $item) {
                $itemHtml = preg_replace_callback('/\{\{item\.([a-zA-Z0-9_]+)\}\}/', function ($m) use ($item) {
                    return $item[$m[1]] ?? '';
                }, $itemTemplate);
                $output .= $itemHtml;
            }
            
            return $output;
        }, $html);

        return $html;
    }

    /**
     * Get nested array value using dot notation
     */
    protected function getNestedValue(array $data, string $key): mixed
    {
        $keys = explode('.', $key);
        $value = $data;
        
        foreach ($keys as $k) {
            if (!is_array($value) || !isset($value[$k])) {
                return null;
            }
            $value = $value[$k];
        }
        
        return $value;
    }

    /**
     * Get template preview
     */
    public function preview(int $templateId): string
    {
        return $this->handleServiceOperation(
            callback: function () use ($templateId) {
                $template = PrintTemplate::findOrFail($templateId);
                
                // Generate sample data for preview
                $sampleData = $this->getSampleData($template->type);
                
                return $this->render($template->type, $sampleData, $template->branch_id);
            },
            operation: 'preview',
            context: ['template_id' => $templateId]
        );
    }

    /**
     * Get sample data for template preview
     */
    protected function getSampleData(string $type): array
    {
        return [
            'company' => [
                'name' => __('Sample Company'),
                'address' => '123 Business Street, City',
                'phone' => '+1 234 567 890',
                'email' => 'info@sample.com',
                'tax_number' => 'TAX-123456',
                'logo' => '',
            ],
            'document' => [
                'number' => 'INV-2024-001',
                'date' => now()->format('Y-m-d'),
                'due_date' => now()->addDays(30)->format('Y-m-d'),
                'status' => 'pending',
            ],
            'customer' => [
                'name' => __('John Doe'),
                'email' => 'john@example.com',
                'phone' => '+1 987 654 321',
                'address' => '456 Customer Ave, Town',
            ],
            'items' => [
                [
                    'name' => __('Product A'),
                    'qty' => 2,
                    'price' => 100.00,
                    'total' => 200.00,
                ],
                [
                    'name' => __('Product B'),
                    'qty' => 1,
                    'price' => 150.00,
                    'total' => 150.00,
                ],
                [
                    'name' => __('Service C'),
                    'qty' => 3,
                    'price' => 50.00,
                    'total' => 150.00,
                ],
            ],
            'subtotal' => 500.00,
            'discount' => 50.00,
            'tax' => 45.00,
            'total' => 495.00,
            'currency' => '$',
        ];
    }

    /**
     * Duplicate a template
     */
    public function duplicate(int $templateId, ?string $newName = null): PrintTemplate
    {
        return $this->handleServiceOperation(
            callback: function () use ($templateId, $newName) {
                $template = PrintTemplate::findOrFail($templateId);
                
                $newTemplate = $template->replicate();
                $newTemplate->name = $newName ?? $template->name . ' (Copy)';
                $newTemplate->is_default = false;
                $newTemplate->save();
                
                return $newTemplate;
            },
            operation: 'duplicate',
            context: ['template_id' => $templateId]
        );
    }

    /**
     * Delete a template
     */
    public function delete(int $templateId): bool
    {
        return $this->handleServiceOperation(
            callback: function () use ($templateId) {
                $template = PrintTemplate::findOrFail($templateId);
                
                // Don't allow deleting the only default template
                if ($template->is_default) {
                    $otherDefaults = PrintTemplate::where('type', $template->type)
                        ->where('id', '!=', $templateId)
                        ->where('is_active', true)
                        ->count();
                    
                    if ($otherDefaults === 0) {
                        throw new \Exception(__('Cannot delete the only active template.'));
                    }
                }
                
                return $template->delete();
            },
            operation: 'delete',
            context: ['template_id' => $templateId]
        );
    }

    /**
     * Get available placeholders
     */
    public function getPlaceholders(): array
    {
        return self::PLACEHOLDERS;
    }
}
