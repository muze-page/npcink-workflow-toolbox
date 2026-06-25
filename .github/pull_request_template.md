## Scope

- [ ] This change is limited to the stated Toolbox module.
- [ ] Public REST, ability ids, workflow shape, lifecycle, or product boundary docs were updated if changed.
- [ ] No unrelated generated files, local environment files, or cross-repo worktree changes are included.

## Toolbox Boundary

- [ ] Toolbox remains the operator-facing AI tool surface.
- [ ] Provider outputs remain suggestions, planning artifacts, or Core handoff packets.
- [ ] This does not add final WordPress writes from fixed-flow buttons.
- [ ] This does not add a second ability registry, workflow registry, approval store, queue/runtime, scheduler, indexing lifecycle, or provider billing/logging owner.
- [ ] Unsplash/Pixabay/Pexels remain image-source connectors, not AI image generation.

## Verification

- [ ] `composer validate --no-check-publish`
- [ ] `composer test:all`
- [ ] `composer check:wporg`
- [ ] Relevant smoke test if the change touches editor UI, Cloud handoff, Core handoff, activation, or REST routing.

## Risk

- Residual risk:
- Rollback plan:

## Release Impact

- [ ] No release impact.
- [ ] Requires package/release verification.

## Notes

Summarize the behavior change, boundary decision, and known follow-up.
