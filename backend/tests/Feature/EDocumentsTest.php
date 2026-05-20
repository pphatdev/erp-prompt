<?php

namespace Tests\Feature;

use Illuminate\Http\UploadedFile;

class EDocumentsTest extends TenantTestCase
{
    /**
     * Test eDocuments Module endpoints listing accessibility.
     */
    public function test_documents_module_endpoints()
    {
        $routes = ['/api/v1/folders', '/api/v1/documents'];
        foreach ($routes as $route) {
            $this->tenantRequest('GET', $route)->assertStatus(200);
        }
    }

    /**
     * Test creating folders and uploading files in eDocuments.
     */
    public function test_documents_folder_and_file_upload_workflow()
    {
        // 1. Create a Folder
        $folderPayload = [
            'name' => 'Finance Audits',
        ];

        $folderResponse = $this->tenantRequest('POST', '/api/v1/folders', $folderPayload);
        $folderResponse->assertStatus(201)->assertJsonPath('data.name', 'Finance Audits');

        $folderId = $folderResponse->json('data.id');
        $this->assertNotNull($folderId);

        // 2. Upload a Mock File under the Folder
        $fakeFile = UploadedFile::fake()->create('audit_2026.pdf', 500, 'application/pdf');

        $documentPayload = [
            'title' => 'Annual Audit Report 2026',
            'folder_id' => $folderId,
            'file' => $fakeFile,
        ];

        $docResponse = $this->tenantRequest('POST', '/api/v1/documents', $documentPayload);
        $docResponse->assertStatus(201)
                    ->assertJsonPath('data.title', 'Annual Audit Report 2026')
                    ->assertJsonPath('data.filename', 'audit_2026.pdf');

        $this->assertDatabaseHas('documents', [
            'title' => 'Annual Audit Report 2026',
            'folder_id' => $folderId,
        ]);
    }
}
