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
   - `GITHUB_TOKEN` - Automatically provided by GitHub Actions

### How It Works

1. Create a GitHub issue with `@claude` mention
2. The GitHub Action triggers and analyzes the issue
3. Agent creates a new branch and implements the requested changes
4. Agent runs tests and opens a pull request
5. PR can be refined with additional `@claude` comments

### Example Usage

```markdown
## Add Session Export Feature

@claude please add a CSV export feature for shooting sessions that includes:
- Session date and location
- Weapon type and caliber
- Shot scores and grouping
- Weather conditions if available

The export should be accessible from the session details page.
```

### Supported Triggers

- **New Issues**: `@claude` in issue title or body
- **Issue Comments**: `@claude` in comments
- **PR Review Comments**: `@claude` in review comments

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

1. **Create Issue**: Describe the feature/bug with `@claude` mention
2. **Wait for Agent**: GitHub Action processes the request
3. **Review PR**: Agent creates pull request with implementation
4. **Iterate**: Use `@claude` comments for refinements
5. **Merge**: Merge when satisfied with the changes

### For Local Development

1. **Create Environment**: Use `clone-for-agent.sh` script
2. **Setup Environment**: Run `setup-dev-env.sh`
3. **Development**: AI agent works in isolated environment
4. **Testing**: Run tests and validate changes
5. **Integration**: Submit PR or integrate changes

## Agent Configuration

### GitHub Agent Configuration

The GitHub agent is configured via `.github/workflows/claude.yml`:

```yaml
# Trigger conditions
on:
  issues:
    types: [opened, assigned]
  issue_comment:
    types: [created]
  pull_request_review_comment:
    types: [created]

# Required permissions
permissions:
  contents: write
  pull-requests: write
  issues: write
  id-token: write
```

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

- **Iterative Refinement**: Use `@claude` for incremental changes
- **Testing Requirements**: Always request tests for new features
- **Documentation**: Ask for documentation updates
- **Performance**: Consider performance implications

## Troubleshooting

### GitHub Agent Issues

**Problem**: Agent doesn't respond to `@claude` mention
**Solution**: 
1. Check GitHub App installation
2. Verify `ANTHROPIC_API_KEY` secret
3. Check Action logs for errors

**Problem**: PR creation fails
**Solution**:
1. Check branch permissions
2. Verify repository write access
3. Review Action logs for specific errors

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

You can customize agent behavior by modifying the prompt in `.github/workflows/claude.yml`:

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
