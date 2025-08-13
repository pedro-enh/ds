# Discord Broadcaster Pro - Web Edition

🚀 **Professional Discord Broadcasting Tool**

## Features
- 📨 Mass Direct Message Broadcasting
- 💰 Built-in Wallet System
- 🔒 Discord OAuth Authentication
- 📊 Real-time Progress Tracking

## Deployment on Zeabur

1. **Push to GitHub:**
```bash
git init
git add .
git commit -m "Discord Broadcaster Pro"
git push origin main
```

2. **Deploy on Zeabur:**
- Go to [Zeabur.com](https://zeabur.com)
- Deploy from GitHub repo
- Add environment variables

3. **Environment Variables:**
- `DISCORD_CLIENT_ID`: Your Discord app client ID
- `DISCORD_CLIENT_SECRET`: Your Discord app client secret
- `DISCORD_BOT_TOKEN`: Your Discord bot token
- `DISCORD_REDIRECT_URI`: `https://discord-brodcast.zeabur.app/complete-auth.php`

## Setup Discord Application
1. Go to https://discord.com/developers/applications
2. Create new application
3. Add OAuth2 redirect: `https://discord-brodcast.zeabur.app/complete-auth.php`
4. Copy credentials to Zeabur environment variables

## Usage
1. Login with Discord
2. Access Wallet to manage credits
3. Start broadcasting to server members

---
**Made for Discord community** 🌟
