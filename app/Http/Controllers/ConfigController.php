<?php

namespace App\Http\Controllers;

class ConfigController extends Controller
{
    public function options(): \Illuminate\Http\JsonResponse
    {
        return response()->json([
            'model_styles' => [
                'Traditional Nepali',
                'Elegant Modern',
                'Casual Street',
                'Professional Business',
                'Festive Traditional',
            ],
            'settings' => [
                'Thamel cafe',
                'City street',
                'Mountain backdrop',
                'Studio white background',
                'Heritage palace',
                'Modern office',
                'Garden setting',
            ],
            'aesthetics' => [
                'Rustic, marble table with soft lighting',
                'Dark, luxury display with velvet',
                'Minimalist white background',
                'Natural wood and greenery',
                'Traditional Nepali craft setting',
                'Modern glass and metal',
            ],
            'themes' => [
                'Tihar Festival',
                'Dashain Special',
                'New Year Sale',
                'Summer Collection',
                'Winter Warmth',
                'Flash Sale',
                'Grand Opening',
                'Anniversary Celebration',
            ],
            'service_credits' => [
                'virtual-mannequin'  => 4,
                'product-staging'    => 3,
                'promotional-banner' => 2,
            ],
        ]);
    }
}
