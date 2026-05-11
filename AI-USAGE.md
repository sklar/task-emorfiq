# AI usage

> Použití AI nástrojů je v pořádku, ale budeme rádi za stručné info, jak a kde jste je využil.

AI (Claude) was used as a collaborator throughout this task — iteratively, with me steering direction and the AI proposing concrete options + tradeoffs. Every architectural call was made by me; every accepted suggestion was read before it landed.

## Where

- Rubber-ducking and tradeoff discussion,
- Tooling setup,
- Local dev + Docker setup,
- Mock data scaffolding,
- Web-platform fact-checking,
- Notes and docs writing,
- Debugging.

## What AI did _not_ do

- **Make architectural calls unilaterally**<br>
  every "X over Y" decision was approved by me, often after a back-and-forth on tradeoffs.
- **Replace reading the actual code**<br>
  when the AI's memory of past state conflicted with the current file state, the file state always won.
- **Generate the project end-to-end**<br>
  it scaffolded patterns and proposed options; I owned the integration and the final shape of every file.
