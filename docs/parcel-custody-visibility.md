# Parcel custody visibility

30 October 2026. Read-only evidence presentation added to My Hub, Scan Parcel, transfer member details and Delivery Assignments. No shipment transition, physical event, money calculation, permission, schema or business record changed. No Sorting/Storage module or migration added.

ParcelCustody presents At Hub, Awaiting Rider Pickup, Rider Pickup Confirmed, Received at Next Hub or Awaiting Final Delivery only when supporting receipt/pickup/assignment records exist. Cards include the recorded hub or Rider, evidence timestamp and next role/action. The scanner returns the same description and inserts it using textContent. Cards use Philippine time; scanner serialized timestamps retain their offset.

Origin evidence requires two-sided pickup and chronological hub receipt. Transfer evidence uses dispatched batch ownership, Rider pickup and individual member/leg receipts. Final delivery distinguishes assignment from Rider-recorded departure, explicitly explaining that departure is not a separate Staff release attestation. Intermediate receipt and partial-batch completion remain distinct. The display is a description of last recorded evidence, not independent proof of real-time possession.

Missing/conflicting evidence displays Custody needs review. Recovery and RTS records take precedence over ordinary custody inference, without releasing holds. Another hub's active leg is not exposed through Staff scanner metadata. Company/role/approval checks remain required; underlying mutation services are unchanged.

Changed: new ParcelCustody service and parcel-custody Blade partial; LogisticsController scanner JSON/eager loading; HubTransferController member eager loading; My Hub, scanner, transfer detail and assignment views; parcel-scan.js; new ParcelCustodyTest and an intermediate-hop assertion in HubTransferWorkflowTest. The assignment Rider select also received an accessible label and 44px height.

Verification: **97 regression tests / 1,985 assertions passed** across transfer, authorization, phone/mobile scanner and compensation suites. **2 focused tests / 13 assertions passed** for evidence presentation, dispatch versus pickup, partial member receipt, missing evidence and read-only behavior. Existing multi-hop test confirms Received at Next Hub before onward dispatch. No automatic financial recognition was added.

Eight synthetic Chromium views at 1440px/390px covered the four workspaces without document overflow. Mobile inspection initially exposed a cramped table card; a minimum card width inside the existing scrollable table corrected it. Final assignment selector has an accessible label and 44px height. Camera/USB/real phone hardware and comprehensive accessibility were not tested. PHP/JS syntax and git whitespace checks passed.

Private evidence: parcel-custody-final.xml/json, parcel-custody-focused.xml/json, parcel-custody-browser.txt and custody-* previews/screenshots. No configured-database migration or business-data change was performed. Existing rollout requirements and missing historical evidence remain separate; no timestamps or receipt records were backfilled.
