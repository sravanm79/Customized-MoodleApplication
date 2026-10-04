# local_llmgrader — LLM grading pipeline for assignments

Hands assignment submissions to an LLM with the assignment's rubric, reference solution and guidelines, and keeps
the suggested grade and feedback as a **draft** until a teacher approves it. Moodle 5.0+.

## Pipeline

```
trigger ──> jobs::queue() ──> adhoc task evaluate_submission ──> draft ──(teacher)──> approve: grade + feedback released
  │           (one job per                │                            └──────────> reject: nothing written
  │            submission content)        ├─ submission_content::extract()   online text, code/text files, notebooks
  │                                       ├─ prompt_builder::build()         assignment, rubric, reference, guidelines, max score, submission
  │                                       ├─ evaluator::evaluate()           provider call, JSON checked, totals recomputed
  │                                       └─ review (stale checks; auto-release when review is off)
  ├─ on submit: observer (\mod_assign\event\assessable_submitted)
  ├─ on close: scheduled task queue_closed_assignments (every 15 min; cut-off date, else due date)
  └─ manual: "Assign to LLM" on the Auto Grade page (report.php)
```

## Per-assignment settings (`settings_assign.php`, "LLM settings" on the Auto Grade page)

When to run (on submit / when submissions close / manual only), teacher review (on by default), rubric and criteria
("- Criterion [N marks]" per line), reference solution, marking guidelines. Assignments without settings run on
submit with the site's review default.

## Review (`report.php`, `review.php`)

Auto Grade page (assignment > More > Auto Grade): status counts, "Assign to LLM", "Approve all drafts", per student
the suggested grade and feedback with **Approve**, **Review & edit** (per-criterion breakdown, editable grade and
feedback) and **Reject**. Approving re-checks that the submission is unchanged, then saves the grade and feedback as
the approving teacher (released under marking workflow). Drafts never touch the gradebook.

## Providers (`classes/provider`)

`provider` interface: `complete(array $messages, array $options): ['content', 'model', 'usage']`. Shipped:
`openai_compatible` (vLLM, OpenAI, Ollama, LiteLLM; batch or streamed with server-sent events, setting "Stream
responses") and `mock` (offline, deterministic test replies). Add one by dropping a class implementing the interface
into `classes/provider`; it appears in Site administration > Plugins > Local plugins > LLM grader > LLM provider.

## Safety

- The submission is fenced and labelled as data; the system prompt tells the model to ignore instructions in it.
- Scores are clamped per criterion and totalled by the plugin, never taken from the model.
- A job applies only to the exact content it evaluated (content hash); edited or reverted submissions make it stale.
- Automatic release never overwrites a teacher's grade. Only current, active students are queued.
