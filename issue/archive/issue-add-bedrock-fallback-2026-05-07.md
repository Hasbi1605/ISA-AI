# Issue: Add Amazon Bedrock Fallback

## Context
Memo evaluation generation hit `429` rate limits across the existing GitHub Models, Groq, and Gemini fallback chain. A new Amazon Bedrock API key is available and should be added as an additional fallback path without committing the secret.

## Goals
- Support Amazon Bedrock API keys through `AWS_BEARER_TOKEN_BEDROCK`.
- Add Bedrock text models to the chat fallback lane.
- Keep secrets out of git and production code.
- Verify provider behavior with focused tests.
- Summarize official model pricing references for cheap and expensive Bedrock options.

## Implementation Plan
1. Add a native `bedrock_converse` provider in `python-ai/app/services/llm_streaming.py`.
2. Convert standard chat messages into Amazon Bedrock Converse API payloads.
3. Add Amazon Nova Micro and Amazon Nova Lite as late fallback models in `python-ai/config/ai_config.yaml`.
4. Add regression tests for request shape and response parsing.
5. Set `AWS_BEARER_TOKEN_BEDROCK` only in the production env file and restart the Python AI services.

## Official References Checked
- Amazon Bedrock API keys use `AWS_BEARER_TOKEN_BEDROCK` or `Authorization: Bearer ...`.
- Bedrock Converse accepts `messages`, `system`, and `inferenceConfig` and returns text in `output.message.content`.
- AWS Price List API was used for current `us-east-1` standard text-model pricing.

## API Probe
- `ListFoundationModels` in `us-east-1` returned 93 text-capable models for the supplied Bedrock API key.
- A small Converse request to `amazon.nova-micro-v1:0` in `us-east-1` returned successfully.

## Pricing Notes
- Among standard Bedrock text models in `us-east-1`, Amazon Nova Micro has the lowest input-token price observed at $0.035 per 1M input tokens and $0.140 per 1M output tokens.
- By combined input+output token price, the cheapest observed standard text model was Mistral Voxtral Mini 1.0 at $0.040 input and $0.040 output per 1M tokens.
- Among marketplace-style Bedrock Edition text models observed through the pricing feed, Claude 3 Haiku was the cheapest at $0.250 input and $1.250 output per 1M tokens, while Claude 3 Opus / Claude Opus 4 / Claude Opus 4.1 were the highest at $15 input and $75 output per 1M tokens.

## Risks
- Some Bedrock models may require region availability or model access in the AWS account.
- Marketplace/proprietary models can require subscription or EULA acceptance before invocation.
- Pricing differs by region and tier; the production fallback should prefer low-cost, generally available text models.
