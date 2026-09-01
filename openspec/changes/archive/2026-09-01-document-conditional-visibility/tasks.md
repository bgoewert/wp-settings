## 1. Record the shipped behaviour

- [x] 1.1 Write the `conditional-visibility` delta spec covering reference resolution, field visibility, section visibility, and the order the controlling fields reach the browser in.
- [x] 1.2 Confirm each requirement has a test behind it: `test_init_resolves_a_field_condition_to_the_input_name`, `test_init_leaves_a_condition_that_already_names_the_input_alone`, `test_init_wraps_a_conditional_section_with_its_conditions`, `test_enqueue_admin_prints_the_conditionals_before_the_script`, and `tests/e2e/conditional-visibility.spec.js`.
- [x] 1.3 Sync the delta into `openspec/specs/conditional-visibility/` and archive the change, writing the real Purpose rather than the CLI's TBD stub.
