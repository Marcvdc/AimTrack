# AI Agents Development Setup

This document explains how to set up and use AI agents with the AimTrack project for automated development and issue resolution.

## Overview

The AimTrack project supports two types of AI agent operations:

1. **GitHub Agent** - Automated PR creation from issues via GitHub Actions
2. **Local Agent** - Isolated development environments for local AI development

## GitHub Agent Setup

### Prerequisites

1. **Claude Code GitHub App** must be installed on the repository
2. **Repository Secrets** configured:
   - `ANTHROPIC_API_KEY` - Your Anthropic API key
   - `KJ_PLUGINS_TOKEN` - Fine-grained PAT met **Contents: read** op `kjsoftware/kj-claude-plugins` (de private plugin-marketplace met de KJ-ontwikkelroute). Zonder dit secret faalt de workflow bewust.
   - `GITHUB_TOKEN` - Automatically provided by GitHub Actions
3. **Repository setting** *Allow GitHub Actions to create and approve pull requests* (Settings → Actions → General) aan, anders kan de agent geen PR openen.
4. **Labels** `agent-ready`, `agent-question` en `agent-resume` moeten in de repo bestaan.

### How It Works

De agent draait via `.github/workflows/agent.yml` en kent twee modi.

**Fresh build** — zet het label `agent-ready` op een issue:

1. De workflow triggert en behandelt uitsluitend dat ene issue.
2. De agent bouwt op branch `claude/issue-<n>` (nooit op de default branch) via de KJ-ontwikkelroute (plannen → bouwen → controleren → review → committen → pr).
3. De agent draait tests en opent een pull request. **De agent merget nooit** — de mens keurt.
4. Statuslabels: `agent-working` tijdens de bouw, `agent-question` als de agent een vraag aan een mens heeft.

**Resume** — beantwoord een `agent-question`-issue met een comment (of zet label `agent-resume`):

1. Alleen comments van OWNER/MEMBER/COLLABORATOR triggeren (author-association-gate tegen prompt-injectie).
2. De agent leest de volledige thread en hervat op de bestaande `claude/issue-<n>`-branch.
3. Is het antwoord onvoldoende, dan stelt de agent een gerichte vervolgvraag en blijft op `agent-question`.

### Example Usage

Maak een issue met een heldere omschrijving en acceptatiecriteria, bijvoorbeeld:

```markdown
## Add Session Export Feature

Voeg een CSV-export voor schietsessies toe met:
- Sessiedatum en locatie
- Wapentype en kaliber
- Schotscores en groepering

De export moet bereikbaar zijn vanaf de sessie-detailpagina.
```

Zet vervolgens het label `agent-ready` op het issue. De agent pakt het op en opent een PR.

### Supported Triggers

- **Label `agent-ready`** op een issue → fresh build tot PR
- **Comment op een `agent-question`-issue** (door OWNER/MEMBER/COLLABORATOR) → resume op de bestaande branch
- **Label `agent-resume`** → resume handmatig forceren

### Agent Capabilities

- ✅ Create new branches with descriptive names
- ✅ Implement features following project conventions
- ✅ Add and update tests
- ✅ Generate pull requests with proper linking
- ✅ Refine changes based on review comments
- ✅ Follow PSR-12 and project style guidelines

## Local Agent Setup

### Quick Start

```bash
# Clone the repository
git clone https://github.com/Marcvdc/AimTrack.git
cd AimTrack

# Create an agent environment
./scripts/clone-for-agent.sh claude-agent-1

# Setup the agent environment
cd ../aimtrack-agent-envs/claude-agent-1
./scripts/setup-dev-env.sh
```

### Agent Environment Features

- **Isolated Docker containers** with unique project names
- **Independent git workspace** for each agent
- **Dedicated ports** to avoid conflicts
- **Agent-specific configuration** and logging
- **Clean separation** from main development environment

### Environment Structure

```
aimtrack-agent-envs/
├── claude-agent-1/
│   ├── .ai-logs/          # Agent operation logs
│   ├── .ai-workspace/     # Agent working files
│   ├── .env.local         # Agent-specific configuration
│   └── AGENT_README.md    # Agent setup instructions
└── claude-agent-2/
    └── ...
```

## Development Workflow

### For GitHub Issues

1. **Create Issue**: Beschrijf de feature/bug met acceptatiecriteria
2. **Label `agent-ready`**: zet het label zodat de GitHub Action het oppakt
3. **Review PR**: de agent opent een pull request met de implementatie
4. **Iterate**: beantwoord `agent-question`-comments; de agent hervat op de branch
5. **Merge**: merge zelf wanneer je tevreden bent (de agent merget nooit)

### For Local Development

1. **Create Environment**: Use `clone-for-agent.sh` script
2. **Setup Environment**: Run `setup-dev-env.sh`
3. **Development**: AI agent works in isolated environment
4. **Testing**: Run tests and validate changes
5. **Integration**: Submit PR or integrate changes

## Agent Configuration

### GitHub Agent Configuration

The GitHub agent is configured via `.github/workflows/agent.yml`:

```yaml
# Trigger conditions
on:
  issues:
    types: [labeled]        # label agent-ready / agent-resume
  issue_comment:
    types: [created]        # antwoord op een agent-question-issue

# Required permissions
permissions:
  contents: write
  pull-requests: write
  issues: write
  id-token: write
  actions: read
```

De private KJ-plugin-marketplace (`kjsoftware/kj-claude-plugins`) wordt op runtime geladen via secret `KJ_PLUGINS_TOKEN`; er wordt niets van die private repo in deze repo gecommit.

### Local Agent Configuration

Each agent environment has a `.env.local` file:

```bash
COMPOSE_PROJECT_NAME=aimtrack_claude-agent-1
UID=1000
GID=1000
APP_PORT=8081
FORWARD_DB_PORT=3307
AGENT_NAME=claude-agent-1
AGENT_MODE=true
```

## Best Practices

### Issue Creation

- **Clear Descriptions**: Provide detailed requirements
- **Specific Examples**: Include expected behavior
- **Acceptance Criteria**: Define success conditions
- **Context**: Reference related issues or documentation

### Agent Prompts

- **Be Specific**: "Add email validation" vs "Fix auth"
- **Provide Context**: "For the weapon export CSV feature..."
- **Define Scope**: "Only update the service, don't touch the UI"
- **Reference Patterns**: "Follow the same pattern as session export"

### Code Review

- **Iterative Refinement**: beantwoord `agent-question`-comments voor incrementele bijsturing
- **Testing Requirements**: Always request tests for new features
- **Documentation**: Ask for documentation updates
- **Performance**: Consider performance implications

## Troubleshooting

### GitHub Agent Issues

**Problem**: De agent reageert niet op het label `agent-ready`
**Solution**:
1. Controleer of de Claude Code GitHub App geïnstalleerd is
2. Verifieer de secrets `ANTHROPIC_API_KEY` en `KJ_PLUGINS_TOKEN`
3. Controleer of de labels `agent-ready`/`agent-question`/`agent-resume` bestaan
4. Bekijk de Action-logs voor fouten

**Problem**: PR creation fails
**Solution**:
1. Zet de repo-setting *Allow GitHub Actions to create and approve pull requests* aan
2. Verifieer repository write-rechten
3. Bekijk de Action-logs voor specifieke fouten

### Local Agent Issues

**Problem**: Docker port conflicts
**Solution**:
1. Check `.env.local` for port assignments
2. Stop conflicting containers
3. Use different agent name

**Problem**: Tests fail in agent environment
**Solution**:
1. Ensure database migrations ran
2. Check environment configuration
3. Verify dependencies installed

## Security Considerations

### GitHub Agent

- **No Secrets**: Agent never commits secrets or API keys
- **Limited Permissions**: Minimal required GitHub permissions
- **Sandboxed**: Runs in temporary GitHub Actions environment
- **Auditable**: All actions logged in GitHub Actions

### Local Agent

- **Isolated Networks**: Each agent in separate Docker network
- **Resource Limits**: Container resource constraints
- **Clean Separation**: No access to host system files
- **Temporary**: Environments can be easily destroyed

## Advanced Usage

### Custom Agent Prompts

You can customize agent behavior by modifying the prompts in `.github/workflows/agent.yml`:

```yaml
prompt: |
  You are a specialized Laravel developer for AimTrack...
  # Custom instructions here
```

### Multiple Agent Environments

Create multiple specialized agents:

```bash
./scripts/clone-for-agent.sh frontend-agent
./scripts/clone-for-agent.sh backend-agent
./scripts/clone-for-agent.sh testing-agent
```

### Integration with CI/CD

The agent integrates with existing CI/CD pipelines:

- **CI Pipeline**: Runs tests and linting
- **Quality Gates**: Enforces code standards
- **Deployment**: Automated deployment after merge
- **Monitoring**: Tracks agent performance

## Contributing

To contribute to the AI agent setup:

1. **Test Changes**: Use agent environments for testing
2. **Update Documentation**: Keep this documentation current
3. **Share Workflows**: Document successful agent workflows
4. **Report Issues**: Create issues for agent improvements

## Resources

- [GitHub Actions Documentation](https://docs.github.com/en/actions)
- [Claude Code Documentation](https://code.claude.com/docs)
- [Laravel Documentation](https://laravel.com/docs)
- [Filament Documentation](https://filamentphp.com/docs)
- [Docker Documentation](https://docs.docker.com)

---

*Last updated: $(date)*
*For questions or issues, create a GitHub issue with the `ai-agents` label.*
