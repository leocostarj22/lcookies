# Fase 0 — Planeamento

**Data de conclusão:** 2026-10-01

## O que foi feito
- Definidos recursos de backend, frontend, API e modelo de dados (`docs/PLAN.md`).
- Criado sistema de acompanhamento: `TODO.md`, `CLAUDE.md`, `docs/fases/`.

## Decisões tomadas (e porquê)
- Namespace `Lcsilva\...` — vendor do autor; `Lcookies` com uma só maiúscula evita problemas de capitalização em servidores Linux.
- Sem IAB TCF — o site não usa anúncios programáticos.
- Sem geo-targeting — público essencialmente UE; evita base de dados GeoIP e mantém compatibilidade com cache.
- IP truncado + hash com salt — prova de consentimento sem guardar o IP em claro.
- Bloqueio sempre no servidor, desbloqueio no cliente — compatível com cache de página.
- Idiomas pt-PT e en-GB.

## Pendente / transita para a fase seguinte
- Iniciar Fase 1 (Fundação).
