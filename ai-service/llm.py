import os, json, time
from openai import OpenAI
from pathlib import Path

_client: OpenAI | None = None

def get_client() -> OpenAI:
    global _client
    if _client is None:
        _client = OpenAI(
            base_url=os.getenv("LLM_BASE_URL", "http://localhost:11434/v1"),
            api_key=os.getenv("LLM_API_KEY", "ollama"),
        )
    return _client

def load_prompt(name: str) -> str:
    path = Path(__file__).parent / "prompts" / f"{name}.txt"
    return path.read_text()

def load_fixture(name: str) -> str:
    path = Path(__file__).parent / "fixtures" / f"{name}.json"
    return path.read_text()

def llm_chat(
    messages: list[dict],
    temperature: float = 0.2,
    json_mode: bool = False,
) -> str:
    if os.getenv("LLM_ENABLED", "true").lower() != "true":
        raise RuntimeError("LLM disabled — use load_fixture()")

    model = os.getenv("LLM_MODEL", "llama3.1")
    kwargs = {"model": model, "messages": messages, "temperature": temperature}
    if json_mode:
        kwargs["response_format"] = {"type": "json_object"}

    resp = get_client().chat.completions.create(**kwargs)
    return resp.choices[0].message.content

def llm_chat_retry(messages, temperature=0.2, json_mode=False, retries=3) -> str:
    for attempt in range(retries):
        try:
            return llm_chat(messages, temperature, json_mode)
        except Exception as e:
            if attempt == retries - 1:
                raise
            time.sleep(2 ** attempt)
