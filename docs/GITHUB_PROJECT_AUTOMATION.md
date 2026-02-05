# GitHub Project Automation Setup

> This guide helps you configure GitHub Projects to automatically move issues through your workflow

## 🎯 Project Board Structure

Create a project board with these columns:
- **Backlog** - Groomed and ready stories
- **Ready** - Selected for current sprint
- **In Progress** - Currently being worked on
- **In Review** - PR opened, awaiting review
- **Done** - Merged and deployed

## 🤖 Automated Workflows

### Option 1: Built-in GitHub Projects Automation

1. **Go to your Project Settings**
   - Navigate to: `https://github.com/users/tharindarodrigo/projects/YOUR_PROJECT_NUMBER/settings/workflows`

2. **Enable these workflows:**

#### Workflow 1: Move to "In Review" when PR opens
```yaml
Trigger: Pull request opened
Condition: Linked issue exists
Action: Move issue to "In Review"
```

#### Workflow 2: Move to "In Progress" when PR is converted to draft
```yaml
Trigger: Pull request converted to draft
Condition: Linked issue exists
Action: Move issue to "In Progress"
```

#### Workflow 3: Move to "Done" when PR merges
```yaml
Trigger: Pull request merged
Condition: Linked issue exists
Action: Move issue to "Done"
```

#### Workflow 4: Move to "In Progress" when assigned
```yaml
Trigger: Issue assigned
Action: Move issue to "In Progress"
```

### Option 2: GitHub Actions Automation (More Powerful)

Create `.github/workflows/project-automation.yml`:

```yaml
name: Project Board Automation

on:
  pull_request:
    types: [opened, reopened, closed, converted_to_draft, ready_for_review]
  issues:
    types: [assigned, unassigned]

jobs:
  move-linked-issue:
    runs-on: ubuntu-latest
    steps:
      - name: Move issue based on PR status
        uses: alex-page/github-project-automation-plus@v0.9.0
        with:
          project: LMU IoT Portal
          column: ${{ github.event.pull_request.draft && 'In Progress' || 'In Review' }}
          repo-token: ${{ secrets.GITHUB_TOKEN }}
```

## 📋 Labels Setup

Create these labels in your repository:

```bash
# Area labels
area:db              - Database/migrations
area:filament        - Filament UI
area:ingestion       - Telemetry ingestion
area:sim             - Device simulation
area:report          - Documentation/reports

# Type labels
type:story           - User story
type:task            - Technical task
type:bug             - Bug fix
type:hotfix          - Urgent fix

# Priority labels
prio:P0              - Critical/blocking
prio:P1              - High priority
prio:P2              - Medium priority
prio:P3              - Low priority

# Status labels (auto-applied by workflows)
status:in-review     - PR opened
status:blocked       - Waiting on dependencies
status:needs-changes - Review feedback pending
```

## 🔗 Issue Linking Best Practices

### In Pull Requests
Use one of these formats in your PR description:

```markdown
Closes #1
Fixes #42
Resolves #7
```

This will:
1. ✅ Link the PR to the issue
2. ✅ Trigger automation workflows
3. ✅ Auto-close issue when PR merges

### Multiple Issues
```markdown
Closes #1, #2
Addresses #3
Part of #4
```

## 🎨 Custom Automations (Advanced)

### Auto-label PRs based on files changed

Create `.github/labeler.yml`:

```yaml
area:db:
  - database/**/*

area:filament:
  - app/Filament/**/*

area:ingestion:
  - app/Domain/IoT/**/*

type:docs:
  - '*.md'
  - docs/**/*
```

Then add workflow `.github/workflows/label.yml`:

```yaml
name: "Pull Request Labeler"
on:
  pull_request:
    types: [opened, synchronize]

jobs:
  label:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/labeler@v4
        with:
          repo-token: "${{ secrets.GITHUB_TOKEN }}"
```

## 🚀 One-Click Setup (Recommended)

### For Project Owner:

1. **Create GitHub Project**
   ```
   https://github.com/users/tharindarodrigo/projects/new
   ```
   - Template: "Team backlog"
   - Name: "LMU IoT Portal Development"

2. **Link Repository**
   - In project settings → "Manage access"
   - Add repository: `lmu-iot-portal`

3. **Configure Built-in Automations**
   - Go to project → Settings → Workflows
   - Enable:
     - "Item closed" → Move to "Done"
     - "Pull request merged" → Move to "Done"
     - "Pull request opened" → Move to "In Review"

4. **Import Issues**
   ```bash
   # All issues will automatically appear in "Backlog"
   # Manually organize them into Ready/In Progress as needed
   ```

## 📊 Monitoring & Metrics

View automated insights:
- Cycle time (how long issues stay in each column)
- Velocity (issues completed per week)
- Work in progress limits

Access at: `Project → Insights`

## ⚙️ Manual Overrides

Even with automation, you can manually:
- Drag issues between columns
- Change labels
- Re-open closed issues

Automation augments, not replaces, manual control.

## 🙋 FAQ

**Q: What if I don't link a PR to an issue?**
A: Automation won't trigger. You can still manually move cards.

**Q: Can I have multiple PRs for one issue?**
A: Yes! All linked PRs will affect the same issue's status.

**Q: What if a PR is rejected?**
A: Close the PR without merging → issue stays in "In Review" → manually move back to "In Progress" or "Ready"

---

**Next Steps**: 
1. Create your GitHub Project board
2. Enable built-in automations (easiest)
3. Optional: Add GitHub Actions for advanced features
