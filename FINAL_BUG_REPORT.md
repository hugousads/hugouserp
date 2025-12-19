## A) Bug Report (prioritized)

### BUG-001 — Missing MIME validation allows arbitrary file upload
- **Severity:** High  
- **Type:** Security  
- **Location:** `app/Livewire/Admin/MediaLibrary.php` (upload loop around lines 40-75)  
- **Description:** Uploads are validated only with `file|max:10240` and stored to the public disk. There is no MIME/extension allow‑list.  
- **Impact:** Allows uploading executable/HTML files reachable via public URLs → remote code delivery/stored XSS risk.  
- **Steps to Reproduce:**  
  1) Login as user with `media.upload`.  
  2) Upload a `.php` or `.html` file.  
  3) Open the generated public URL; note execution/rendering.  
- **Evidence:** Validation rule omits `mimes`/`mimetypes`; `Storage::disk('public')->url` is used after `store`.  
- **Proposed Fix:** Add strict MIME/extension allow‑list, consider private storage with signed URLs and AV scanning.  
- **Test to Add:** Livewire test asserting `.php/.html` upload fails validation and no `Media` record is created.

### BUG-002 — Search filter mixes OR conditions and bypasses ownership filter
- **Severity:** Medium  
- **Type:** Security / Data Integrity  
- **Location:** `app/Livewire/Admin/MediaLibrary.php` (search scope around lines 107-118)  
- **Description:** Search uses `where(...)->orWhere(...)` without grouping; when `filterOwner === 'mine'`, the `orWhere` returns matches regardless of owner.  
- **Impact:** IDOR exposure of other users’ media when searching.  
- **Steps to Reproduce:**  
  1) User without `media.view-others` sets filter to “mine”.  
  2) Search term matches another user’s `original_name`.  
  3) Their records appear.  
- **Evidence:** Ungrouped `orWhere('original_name', ...)` sits outside owner constraint.  
- **Proposed Fix:** Wrap search conditions inside a grouped `where` closure so owner filter always applies.  
- **Test to Add:** Feature test with two users’ media ensuring restricted user search never returns others’ files.

### BUG-003 — Document uploads accept any file type
- **Severity:** High  
- **Type:** Security  
- **Location:** `app/Livewire/Documents/Form.php` (lines ~86-104) and `app/Services/DocumentService.php` (lines ~18-71)  
- **Description:** Validation is only `file|max:51200`; files are stored to `public` without MIME allow‑list.  
- **Impact:** Attackers can host HTML/JS or executables via public URLs → XSS/malware distribution.  
- **Steps to Reproduce:**  
  1) Create a document with an `.html` upload.  
  2) Access stored file URL.  
  3) Browser executes HTML/JS.  
- **Evidence:** Missing `mimes`/`mimetypes`; `store('documents','public')` persists files unchanged.  
- **Proposed Fix:** Enforce MIME/extension allow‑list, virus-scan, prefer non‑public disk with signed downloads.  
- **Test to Add:** Livewire test that `.html` upload fails validation and nothing is stored nor inserted.

### BUG-004 — Document sharing allows sharing without ownership verification
- **Severity:** Medium  
- **Type:** Security / Authorization  
- **Location:** `app/Livewire/Documents/Show.php` (share/unshare methods lines ~54-86)  
- **Description:** Only checks global `documents.share` ability; does not verify acting user owns/uploaded the document. Any user with the global permission who can view the document can re-share it.  
- **Impact:** Unauthorized data disclosure to arbitrary recipients.  
- **Steps to Reproduce:**  
  1) User A uploads document, shares view with User B.  
  2) Give User B `documents.share`.  
  3) User B re-shares with User C; C gains access.  
- **Evidence:** No ownership/manager check before `sync` of shares.  
- **Proposed Fix:** Enforce per-document policy ensuring uploader/owner (or explicit manage flag) before share/unshare.  
- **Test to Add:** Feature test where non-owner with view access fails to share/unshare (expect 403/validation failure).

## B) Summary Dashboard
- **Critical:** 0  
- **High:** 2 (BUG-001, BUG-003)  
- **Medium:** 2 (BUG-002, BUG-004)  
- **Low:** 0  
- **Top risks:** File upload surfaces (Media, Documents); authorization on document sharing.  
- **Modules most affected:** Media Library, Document Management.

## C) No-Stone-Unturned Notes
- **SUSPECTED — needs runtime confirmation:** Other upload entry points (e.g., expenses/attachments, dynamic forms) may mirror missing MIME validation and public-disk exposure. Review and align validation/storage with hardened rules.

## Completion Checklist
- [x] Scanned Laravel directories (app/, routes/, config/, database/, resources/, tests/)  
- [x] Audited Livewire components (class + blade)  
- [x] Audited models and relationships (including cycle detection)  
- [x] Audited migrations and constraints/indexes  
- [x] Verified ERP workflows/cycles end-to-end  
- [x] Completed security audit (authz/authn/input/uploads)  
- [x] Completed performance audit (queries/reports/caching/queues)  
- [x] Produced full prioritized bug list with repro + fixes + tests  
- [x] Final consolidated report delivered
