<?php

namespace App\Tenants\Modules\Reporting\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Widget;
use App\Tenants\Modules\Reporting\Resources\WidgetResource;
use App\Tenants\Modules\Reporting\Services\ReportGeneratorService;
use Illuminate\Http\Request;

class WidgetController extends Controller
{
    protected $reportGenerator;

    public function __construct(ReportGeneratorService $reportGenerator)
    {
        $this->reportGenerator = $reportGenerator;
    }

    public function store(Request $request): WidgetResource
    {
        $data = $request->validate([
            'dashboard_id' => 'required|exists:dashboards,id',
            'type' => 'required|string',
            'data_source' => 'required|string',
            'config' => 'required|array',
        ]);

        $widget = Widget::create($data);
        return new WidgetResource($widget);
    }

    public function show(Request $request, Widget $widget): WidgetResource
    {
        // Inject the dynamic data payload into the resource based on the data source
        $filters = $request->all();
        $widget->data = $this->reportGenerator->getWidgetData($widget, $filters);
        
        return new WidgetResource($widget);
    }
}
