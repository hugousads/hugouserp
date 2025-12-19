# Comprehensive Bug Hunt Report

## Bug List

### BUG-001 — Missing MIME validation allows arbitrary file upload (High, Security)
- **Location:** `app/Livewire/Admin/MediaLibrary.php` lines 40-75.
- **Description:** The media library validates uploads only with `file|max:10240` and never restricts MIME types or extensions before storing to the public disk. Any authenticated user with `media.upload` permission can upload executable or script files that will be publicly accessible via `Storage::disk('public')->url`, enabling potential XSS or malware distribution.
- **Impact:** Remote code delivery / stored XSS through uploaded files; exposure of server to malicious binaries; compliance risk.
- **Steps to Reproduce:**
  1. Log in as a user with `media.upload` permission.
  2. Upload a `.php` or `.html` file via the media library.
  3. Access the generated public URL and observe execution/rendering.
- **Evidence:** Validation rule omits MIME restriction and files are written to the public disk in the upload loop.
- **Proposed Fix:** Add strict MIME and extension validation (e.g., `mimes:jpg,jpeg,png,pdf,doc,docx`), consider storing on private disk with signed URLs, and run antivirus scanning before persisting.
- **Test to Add:** Livewire test ensuring uploading a disallowed MIME (e.g., `.php`) fails validation and no `Media` record is created.

### BUG-002 — Search filter mixes OR conditions and bypasses ownership filter (Medium, Security/Data Integrity)
- **Location:** `app/Livewire/Admin/MediaLibrary.php` lines 107-118.
- **Description:** The search scope uses `where(...)->orWhere(...)` without grouping. When combined with the `filterOwner === 'mine'` constraint, the `orWhere` allows records that match the second condition regardless of owner, exposing other users’ files even when the UI is set to “mine” or when the user lacks `media.view-others` permission.
- **Impact:** Insecure direct object reference; users can search and view metadata for files they shouldn’t see.
- **Steps to Reproduce:**
  1. Log in as a user without `media.view-others`.
  2. Set filter to “mine” and search for a term only present in another user’s `original_name`.
  3. Observe records from other users appearing.
- **Evidence:** Unscoped `orWhere('original_name', ...)` sits outside a grouped closure, so it’s OR-ed against the entire query.
- **Proposed Fix:** Wrap search conditions in a single `where` group (`$q->where(fn($q)=>$q->where('name', 'like', ...)->orWhere('original_name', 'like', ...))`), ensuring owner filter applies.
- **Test to Add:** Feature test creating media for two users, asserting that searching as a restricted user never returns other users’ records.

### BUG-003 — Document uploads accept any file type (High, Security)
- **Location:** `app/Livewire/Documents/Form.php` lines 86-104 and `app/Services/DocumentService.php` lines 18-71.
- **Description:** Document upload validation only checks `file|max:51200` and never restricts MIME types. `DocumentService::uploadDocument` immediately stores files to the public disk and records MIME/extension from the client-supplied upload. Attackers with document create permission can store active content (JS/HTML) or executables and distribute via public URLs.
- **Impact:** XSS, malware hosting, potential phishing from ERP domain.
- **Steps to Reproduce:**
  1. Create a document with an `.html` file through the Documents form.
  2. Access the stored file via the generated URL.
  3. Observe browser executes the HTML/JS.
- **Evidence:** Validation lacks `mimes`/`mimetypes`, and `store('documents','public')` persists user-supplied files unchanged.
- **Proposed Fix:** Enforce MIME whitelist (e.g., `mimes:pdf,doc,docx,png,jpg`), optionally virus-scan and store on a non-public disk with signed downloads.
- **Test to Add:** Livewire test asserting `.html` upload fails validation and nothing is stored on disk nor in `documents` table.

### BUG-004 — Document sharing allows assigning shares without verifying document ownership (Medium, Security/Authorization)
- **Location:** `app/Livewire/Documents/Show.php` lines 54-86.
- **Description:** `shareDocument` and `unshare` authorize only via a global `documents.share` ability. There is no check that the acting user owns or uploaded the document, or otherwise has per-document share rights. Any user with the global permission who can view a shared document can re-share it with arbitrary users, bypassing the uploader’s control.
- **Impact:** Unauthorized data disclosure; recipients may gain edit/full access to sensitive documents.
- **Steps to Reproduce:**
  1. User A uploads a private document and shares view access with User B.
  2. Grant User B global `documents.share` ability.
  3. User B opens the document and uses the share form to grant access to User C.
  4. User C gains access without owner approval.
- **Evidence:** Methods call `authorize('documents.share')` but never compare `$document->uploaded_by` or ownership before syncing shares.
- **Proposed Fix:** Enforce per-document policy (e.g., policy method ensuring uploader/owner or explicit share-with-manage flag) before allowing share/unshare actions.
- **Test to Add:** Feature test where a non-owner with view access but without ownership fails to share/unshare; expect 403 or validation error.

## Summary Dashboard
- Critical: 0
- High: 2 (BUG-001, BUG-003)
- Medium: 2 (BUG-002, BUG-004)
- Low: 0

Modules most affected: Media Library, Document Management.

## No-Stone-Unturned Notes
- **SUSPECTED:** Additional file upload entry points exist (Expenses attachments, dynamic forms). These should be reviewed for similar MIME validation gaps and public disk exposure.
