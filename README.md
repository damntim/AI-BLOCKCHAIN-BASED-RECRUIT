# RecruitChain

RecruitChain is a modern, AI-powered recruitment platform with blockchain-verified hiring. It features an automated recruitment pipeline: Job Posting → Resume Screening → Anti-Cheat Exam → AI Conversational Interview → Hiring Results. All critical milestones are immutably sealed on the Internet Computer (ICP) blockchain.

## Tech Stack
* **Frontend/Core:** PHP 8.2, Alpine.js, Tailwind CSS (via CDN for dev)
* **Database:** MySQL 8.0
* **AI Service:** Python, FastAPI, OpenAI-compatible LLM
* **Anti-Cheat Service:** Python, FastAPI, WebSockets
* **Blockchain Gateway:** Node.js, Express, Internet Computer (DFX / Motoko)

## Quick Start (Development)

### 1. Database Setup
Ensure you have a MySQL server running (configured by default for `127.0.0.1:3334`, root/123).
Run the setup script from the root directory to create the database, run migrations, and create the default admin user:
```bash
php setup.php
```

### 2. Main Web Application
Start the built-in PHP development server:
```bash
php -S localhost:8800 -t .
```

### 3. Background Worker (Queue)
In a new terminal, start the PHP queue worker which handles async tasks like screening and generating exams:
```bash
php queue/worker.php
```

### 4. AI Service
In a new terminal, navigate to the `ai-service` directory, install dependencies, and run the FastAPI server:
```bash
cd ai-service
pip install -r requirements.txt
uvicorn main:app --port 8001 --reload
```
*Note: By default, LLM generation is mocked. Set `LLM_ENABLED=true` in `ai-service/.env` to use real models.*

### 5. Anti-Cheat Service
In a new terminal, navigate to the `anti-cheat-service` directory:
```bash
cd anti-cheat-service
pip install -r requirements.txt
uvicorn main:app --port 8002 --reload
```

## Blockchain Deployment (ICP Gateway)

The blockchain infrastructure consists of a Motoko canister (`icp/`) and a Node.js API Gateway (`icp-gateway/`). You mentioned that ICP might run on an external environment (like WSL, a dedicated server, or Production mainnet). 

### Step 1: Deploy the Canister (in your external environment / WSL)
1. Install [dfx](https://internetcomputer.org/docs/current/developer-docs/getting-started/install).
2. Navigate to the `icp` folder.
3. Start the local replica: `dfx start --background --clean`
4. Deploy the canister: `dfx deploy`
5. Note the generated **Canister ID** from the output.

### Step 2: Configure the Node.js Gateway
The PHP app communicates with the `icp-gateway`, which securely signs transactions and submits them to the canister.
Navigate to the `icp-gateway` directory:
```bash
cd icp-gateway
npm install
```

Edit the `icp-gateway/.env` file:
```ini
PORT=3001

# Set this to the external/WSL host where the replica/mainnet is running.
# Example for local WSL: http://127.0.0.1:4943
# Example for Mainnet: https://ic0.app
ICP_HOST=http://localhost:4943

# The Canister ID you received in Step 1
CANISTER_ID=aaaaa-aa...

# The path to the pem file for the identity executing the transactions
IDENTITY_PEM_PATH=./identity.pem
```

Start the gateway:
```bash
node index.js
```

### Step 3: Connect the PHP App
In your root `.env` file, ensure the URL points to your running gateway, and enable the blockchain feature flag:
```ini
ICP_GATEWAY_URL=http://localhost:3001
BLOCKCHAIN_ENABLED=true
```

## Feature Flags

The root `.env` file controls the platform's behavior. By default, all external services are "mocked" for easy local UI development. Toggle these flags to `true` to use the real microservices:

* `BLOCKCHAIN_ENABLED`: If true, PHP connects to the Node.js ICP gateway. If false, it mocks a success response.
* `LLM_ENABLED`: If true, the AI service uses OpenAI/Ollama APIs. If false, it returns pre-configured mock JSONs from `fixtures/`.
* `FACE_VERIFY_ENABLED`: If true, requires successful WebRTC face capture and descriptor matching via `face-api.js` to bypass authentication gates.

## Default Credentials
**Admin User:**
Email: `admin@recruitchain.app`
Password: `admin123`
