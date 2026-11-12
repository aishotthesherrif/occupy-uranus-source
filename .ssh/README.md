# SSH Keys for Deployment

This directory contains SSH keys for automated deployment to the production server.

## Files

- **`deploy_key`** - Private SSH key (gitignored, do not share)
- **`deploy_key.pub`** - Public SSH key (tracked in git, safe to share)

## Setup

The public key has been generated and is ready to add to your webhost.

### Public Key

```
ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAIIuiJyfzZ1bMcgIprJqui+v1CM2Mi5O7USzX1OLiy310 occupy-uranus-deploy
```

### Add to Webhost

**Option 1: Via cPanel / Control Panel**
1. Log into your hosting control panel
2. Go to **SSH Access** or **SSH Keys**
3. Select **Import Key** or **Manage Keys**
4. Paste the public key above
5. Click **Authorize** (if required)

**Option 2: Via SSH (if you have password access)**
```bash
cat .ssh/deploy_key.pub | ssh your_username@ethlovesuranus.space "mkdir -p ~/.ssh && cat >> ~/.ssh/authorized_keys && chmod 600 ~/.ssh/authorized_keys"
```

**Option 3: Via SFTP**
1. SFTP to your server
2. Navigate to `~/.ssh/` (create if doesn't exist)
3. Edit `authorized_keys`
4. Paste the public key on a new line
5. Set permissions: `chmod 600 ~/.ssh/authorized_keys`

## Test Connection

After adding the key to your server:

```bash
ssh -i .ssh/deploy_key your_username@ethlovesuranus.space
```

If it connects without asking for a password, you're ready to deploy!

## Security

- ✅ Private key (`.ssh/deploy_key`) is gitignored
- ✅ Private key has restricted permissions (600)
- ✅ Public key (`.ssh/deploy_key.pub`) is safe to commit
- ✅ Keys are project-local (not in ~/.ssh/)

## Regenerate Keys

If you need to regenerate the keys:

```bash
rm .ssh/deploy_key*
ssh-keygen -t ed25519 -C "occupy-uranus-deploy" -f .ssh/deploy_key -N ""
chmod 600 .ssh/deploy_key
chmod 644 .ssh/deploy_key.pub
```

Then re-add the new public key to your webhost.
