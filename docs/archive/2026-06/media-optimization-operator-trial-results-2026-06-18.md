# Media Optimization Operator Trial Results - 2026-06-18

Status: passed on local `https://npcink.local`.

This records the first real-attachment validation for `media_optimization_v1`.
The trial used low-risk local media attachments with `public_content_refs=0`.
It did not use smoke fixture attachments and did not run a whole-library batch.

## Gates

- `composer test:all`: passed, 1775 static contract checks.
- `composer smoke:media-derivative-core`: passed.
- `composer smoke:media-derivative-batch-execute`: passed.
- `composer validate --no-check-publish`: passed.

## Candidate Packet

| Item | Attachment id | MIME before | Public content refs | File before |
| --- | --- | --- | --- | --- |
| 1 | 283934 | image/jpeg | 0 | `2026/06/post-278528-featured-image-3.jpg` |
| 2 | 283888 | image/jpeg | 0 | `2026/06/post-278528-featured-image-1.jpeg` |
| 3 | 283887 | image/jpeg | 0 | `2026/06/post-278528-featured-image.jpeg` |
| 4 | 283886 | image/jpeg | 0 | `2026/06/post-278528-featured-image-2.jpg` |
| 5 | 283869 | image/jpeg | 0 | `2026/06/post-278528-featured-image-1.jpg` |

## Execution Record

| Item | Attachment id | Action | Preview ok | Preflight clear | Proposal id | Executed | Restored | Blocked or failed reason |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| 1 | 283934 | single | yes | yes | `20a36bf6-fd62-4625-8c32-675c913eab43` | yes | yes |  |
| 2 | 283888 | batch | yes | yes | `fb25a06d-80d2-4591-a904-39661ef91898` | yes | yes |  |
| 3 | 283887 | batch | yes | yes | `b0ce5a2c-65af-4fd3-a663-a2061a44af0f` | yes | yes |  |
| 4 | 283886 | batch | yes | yes | `0b86d4bd-c158-432c-acff-dbffa7d5f750` | yes | yes |  |
| 5 | 283869 | batch | yes | yes | `e6f6d22b-5d4b-4969-aa5f-3e2e8fea6e42` | yes | yes |  |

## Restore Record

| Attachment id | Backup id | Restore proposal id |
| --- | --- | --- |
| 283934 | `cloud_media_replace_20260618_034406_c5ff0e10` | `780fb62b-e489-4b85-b2e4-4e1b48a2ba3d` |
| 283888 | `cloud_media_replace_20260618_034406_5b773cbf` | `2d67849d-6042-41c3-bcea-d162965af76c` |
| 283887 | `cloud_media_replace_20260618_034413_1c28077c` | `9e9777ec-4220-4512-a790-d651e5d13f60` |
| 283886 | `cloud_media_replace_20260618_034419_9b584e7a` | `ecc76165-324e-4553-8265-1e51c003ff2a` |
| 283869 | `cloud_media_replace_20260618_034425_db455ea3` | `82398ecc-10c9-47df-85b0-7e527705ed18` |

## Post-Restore Check

After governed restore, all five attachments were checked with a read-only
WP-CLI query:

| Attachment id | MIME after restore | Public content refs | File after restore | History count |
| --- | --- | --- | --- | --- |
| 283934 | image/jpeg | 0 | `2026/06/post-278528-featured-image-3.jpg` | 2 |
| 283888 | image/jpeg | 0 | `2026/06/post-278528-featured-image-1.jpeg` | 2 |
| 283887 | image/jpeg | 0 | `2026/06/post-278528-featured-image.jpeg` | 2 |
| 283886 | image/jpeg | 0 | `2026/06/post-278528-featured-image-2.jpg` | 2 |
| 283869 | image/jpeg | 0 | `2026/06/post-278528-featured-image-1.jpg` | 2 |

## Aggregate Counts

- sampled_count: 5
- previewed_count: 5
- submitted_count: 5
- executed_count: 5
- restored_count: 5
- blocked_count: 0
- failed_count: 0
- partial_success_count: 0

## Decision

The current `media_optimization_v1` path is validated for one single-image
execution plus one selected batch review set on real low-risk local
attachments. The validated path is still:

```text
Toolbox fixed media surface
-> Adapter selected preview/run path
-> Core proposal
-> Adapter approve-and-execute
-> Abilities media replacement
-> governed restore when needed
```

This does not justify whole-library replacement, unattended media mutation,
video transcoding, or another batch write surface. The next batch candidate can
move to a bounded media ALT/caption review set.
