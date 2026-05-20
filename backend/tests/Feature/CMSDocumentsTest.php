<?php

namespace Tests\Feature;

use Illuminate\Http\UploadedFile;

class CMSDocumentsTest extends TenantTestCase
{
    /**
     * Test Documents (CMS) Module endpoints listing accessibility.
     */
    public function test_cms_documents_module_endpoints()
    {
        $routes = ['/api/v1/cms-folders', '/api/v1/cms-documents'];
        foreach ($routes as $route) {
            $this->tenantRequest('GET', $route)->assertStatus(200);
        }
    }

    /**
     * Test CMS Documents module including Folders, Uploads, Checkouts, and Checkins.
     */
    public function test_cms_document_checkout_checkin_workflow()
    {
        // 1. Create a CMS Folder
        $folderPayload = [
            'name' => 'Engineering Manuals',
        ];

        $folderResponse = $this->tenantRequest('POST', '/api/v1/cms-folders', $folderPayload);
        $folderResponse->assertStatus(201);
        $folderId = $folderResponse->json('data.id');

        // 2. Upload a CMS Document
        $fakeFile = UploadedFile::fake()->create('manual_v1.pdf', 1000, 'application/pdf');

        $docPayload = [
            'title' => 'ERP Operations Manual',
            'cms_folder_id' => $folderId,
            'file' => $fakeFile,
        ];

        $docResponse = $this->tenantRequest('POST', '/api/v1/cms-documents', $docPayload);
        $docResponse->assertStatus(201);
        $docId = $docResponse->json('data.id');
        $this->assertNotNull($docId);

        // 3. Checkout the Document (Locking it)
        $checkoutResponse = $this->tenantRequest('POST', "/api/v1/cms-documents/{$docId}/checkout");
        $checkoutResponse->assertStatus(200)->assertJsonPath('data.locked_by.id', $this->admin->id);

        // 4. Checkin the Document (Uploading new version & unlocking it)
        $fakeFileV2 = UploadedFile::fake()->create('manual_v2.pdf', 1200, 'application/pdf');

        $checkinPayload = [
            'file' => $fakeFileV2,
            'change_summary' => 'Updated the installation manual section.',
        ];

        $checkinResponse = $this->tenantRequest('POST', "/api/v1/cms-documents/{$docId}/checkin", $checkinPayload);
        $checkinResponse->assertStatus(200)->assertJsonPath('data.locked_by', null);

        $this->assertDatabaseHas('cms_document_versions', [
            'cms_document_id' => $docId,
            'version_number' => 2,
            'change_summary' => 'Updated the installation manual section.',
        ]);
    }
}
