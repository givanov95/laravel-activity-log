# laravel-activity-log — дневник на действията (кой какво е направил) за Laravel

Laravel package (PHP 8.3, illuminate 11–13, PHPUnit, Larastan). Комуникация с потребителя: български. Код, commit-и и PR-и: английски.

Работният флоу (issue-та, PR-и) идва от плъгина `gws@claude-flow` — `/gws:issue <N>`. Този файл носи само спецификите на проекта.

## Branch-ове
- Базов branch: `main`. Issue branch-ове: `fix|feat|chore/N-kratko-ime` от него, PR към него, squash merge.
- Issue-то се затваря с `Fixes #N` в тялото на commit-а (базовият branch е default — затваря се при merge на PR-а).

## Deploy
- Няма — проектът не се качва на сървър. `/gws:ship` не е приложим тук; доставката е merge в базовия branch и таг `vX.Y.Z` (Packagist се обновява от GitHub hook-а).

## Build и commit-и
- Няма build стъпка (чист PHP package, без npm).
- Тестове: `composer test` (PHPUnit + Testbench, sqlite in-memory); статичен анализ: `composer analyse` (Larastan).
- Pre-commit hook от `givanov95/laravel-git-hooks` (quality gate); при нужда се прескача със `SKIP_HOOK=1`.
- Commit стил: Conventional Commits на английски (`fix(scope): ...`).

## Публичен API
- Пакетът се ползва от няколко наши проекта. Промяна в публичния API (`ActivityLog`, `PendingActivity`, `Actor`, `Changes`, схемата) е breaking → major версия.
- Repo-то е публично: в issue-та, PR-и, README и commit-и не споменавай клиентски/частни проекти.

## GitHub Project board

- Board: „GWS Board“ — project #1, owner `gwebsolutions-dev`
