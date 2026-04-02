<?php

declare(strict_types=1);

namespace App\Controllers;

use Arcos\Core\Helpers\ErrorHelper;
use Arcos\Core\Helpers\ResponseHelper;
use Arcos\Core\Http\Request;
use Arcos\Core\Http\Response;
use App\Services\InventoryService;

class ProductsController
{
    private array $products = [
        1 => ['id' => 1, 'name' => 'Laptop',     'price' => 999.99],
        2 => ['id' => 2, 'name' => 'Mouse',       'price' => 29.99],
        3 => ['id' => 3, 'name' => 'Keyboard',    'price' => 79.99],
    ];

    public function __construct(
        private readonly InventoryService $inventory,
    ) {}

    public function index(Request $request): Response
    {
        $products = array_values($this->products);

        return ResponseHelper::ok($products);
    }

    public function show(Request $request): Response
    {
        $id = (int) $request->input('id');

        if (!isset($this->products[$id])) {
            return ErrorHelper::respond('RES-001');
        }

        $product = $this->products[$id];
        $stock   = $this->inventory->getStock($id);

        if ($stock['status'] === 'down') {
            return ErrorHelper::respond('SYS-002');
        }

        $product['stock'] = $stock['status'] === 'ok'
            ? $stock['quantity']
            : 0;

        return ResponseHelper::ok([$product]);
    }

    public function store(Request $request): Response
    {
        $name  = $request->input('name');
        $price = $request->input('price');

        if (!$name || !$price) {
            return ErrorHelper::respond('VAL-001', "The fields 'name' and 'price' are required.");
        }

        if (!is_numeric($price) || $price <= 0) {
            return ErrorHelper::respond('VAL-002', "The field 'price' must be a positive number.");
        }

        $id                 = max(array_keys($this->products)) + 1;
        $product            = ['id' => $id, 'name' => $name, 'price' => (float) $price];
        $this->products[$id] = $product;

        return ResponseHelper::created([$product]);
    }

    public function update(Request $request): Response
    {
        $id = (int) $request->input('id');

        if (!isset($this->products[$id])) {
            return ErrorHelper::respond('RES-001');
        }

        $name  = $request->input('name');
        $price = $request->input('price');

        if (!$name && !$price) {
            return ErrorHelper::respond('VAL-001', "Provide at least 'name' or 'price' to update.");
        }

        if ($price !== null && (!is_numeric($price) || $price <= 0)) {
            return ErrorHelper::respond('VAL-002', "The field 'price' must be a positive number.");
        }

        if ($name)  $this->products[$id]['name']  = $name;
        if ($price) $this->products[$id]['price'] = (float) $price;

        return ResponseHelper::ok([$this->products[$id]]);
    }

    public function destroy(Request $request): Response
    {
        $id = (int) $request->input('id');

        if (!isset($this->products[$id])) {
            return ErrorHelper::respond('RES-001');
        }

        unset($this->products[$id]);

        return ResponseHelper::message("Product [{$id}] was deleted successfully.");
    }
}