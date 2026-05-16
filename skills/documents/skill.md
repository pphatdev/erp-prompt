---
name: document-management
description: Build advanced document workflows, versioning, and secure storage solutions.
---
# Document Management

Use this skill when building advanced document workflows, versioning, and secure storage solutions.

## Workflows
1. **Document Versioning**: Manage file check-ins and check-outs while maintaining a clear audit trail of changes.
2. **OCR Processing**: Automatically extract text and metadata from uploaded images and PDF scans.
3. **Retention Policy Enforcement**: Automate the lifecycle of documents from active use to archival and deletion.

## Guidelines

### 1. Advanced Versioning
- **Check-in/Check-out**: Prevent concurrent editing by locking documents during updates.
- **Diffs**: Provide visual diffs for text-based documents or clear version histories.

### 2. Storage & Encryption
- **Encrypted at Rest**: Ensure sensitive documents are encrypted before being saved to disk.
- **Multi-Cloud**: Support S3, Azure Blob, or local storage via Laravel Flysystem.

### 3. Automated Workflows
- **OCR**: Integrate Optical Character Recognition to extract text from scanned documents.
- **Retention Policies**: Automate the archival or deletion of documents based on tenant rules.

## Best Practices
- **Metadata Search**: Index custom metadata for instant retrieval.
- **Access Logs**: Log every view and download for highly sensitive documents.
- **Preview Engine**: Use a robust preview engine that doesn't require downloading files to the local machine.

## Troubleshooting
- **Corruption**: Implement checksum verification for all file uploads.
- **Access Denied**: Verify the `DocumentPolicy` is correctly checking both role permissions and individual document ownership.
- **Slow Indexing**: Move OCR and indexing tasks to a high-priority background queue.
