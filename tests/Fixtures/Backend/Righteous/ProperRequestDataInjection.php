<?php

namespace App\Http\Controllers;

use App\Http\Requests\ExportTemplateEditorRequest;
use App\Http\View\Reports\ExportTemplateEditorPage;
use App\Models\ExportTemplate;
use Illuminate\Http\Response;
use Inertia\Inertia;

class ExportTemplateController extends Controller
{
    public function create(ExportTemplateEditorRequest $request): Response
    {
        return Inertia::render(
            'Reports/TemplateEditor',
            ExportTemplateEditorPage::from([
                'templateId' => 'some-id',
            ])
        );
    }

    public function edit(ExportTemplateEditorRequest $request, ExportTemplate $exportTemplate): Response
    {
        return Inertia::render(
            'Reports/TemplateEditor',
            ExportTemplateEditorPage::from([
                'templateId' => $exportTemplate->id,
            ])
        );
    }
}
