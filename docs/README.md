# Fintoc_Payment — Documentation Hub

This folder contains the technical documentation, UML diagrams, and the user guide for the Magento 2 module Fintoc_Payment.

Generated: 2025-09-16 20:25 local time

Contents
- Technical overview: ./technical-overview.md
- Architecture and flows: ./architecture.md
- System configuration: ./configuration.md
- Database schema: ./database-schema.md
- Webhook processing flow: ./webhook-flow.md
- Developer guide (extension points, logging, testing): ./developer-guide.md
- User guide (setup/use): ./user-guide.md
- UML class diagrams:
  - PlantUML: ./uml-class-diagram.puml
  - Mermaid (for quick Git hosting previews): ./uml-class-diagram.mmd

How to render UML
- PlantUML (.puml)
  - Use any PlantUML renderer (local docker, IntelliJ/VSCode plugins, or https://www.plantuml.com/plantuml/).
  - Example CLI: docker run --rm -v "$PWD":/data plantuml/plantuml -tsvg docs/uml-class-diagram.puml
- Mermaid (.mmd)
  - Many Git platforms render Mermaid automatically.
  - You can also use the Mermaid Live Editor: https://mermaid.live/

Contributing to docs
- Keep files small and focused; link back to this README for navigation.
- When adding new services, update the UML diagram(s) and the relevant sections of architecture.md and technical-overview.md.
