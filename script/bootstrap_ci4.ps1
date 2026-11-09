<#
.SYNOPSIS
    Bootstrap du projet CodeIgniter 4 (Gestion RH) a la racine du depot.

.DESCRIPTION
    1. Installe le squelette officiel codeigniter4/appstarter via Composer
       (dans un dossier temporaire), puis le copie a la racine du depot sans
       ecraser les dossiers metier existants (conception/, script/, demo/, ...).
    2. Cree l'arborescence metier du projet (domaines auth / recrutement / personnel).
    3. Prepare .env a partir du modele fourni par CI4.

.EXAMPLE
    powershell -ExecutionPolicy Bypass -File .\script\bootstrap_ci4.ps1

.EXAMPLE
    # CI4 deja installe : ne creer que les dossiers manquants
    powershell -ExecutionPolicy Bypass -File .\script\bootstrap_ci4.ps1 -SkipComposer
#>
param(
    [switch]$SkipComposer
)

$ErrorActionPreference = 'Stop'

# Racine du depot = dossier parent de /script
$Root = Split-Path -Parent $PSScriptRoot
Write-Host "Depot : $Root" -ForegroundColor Cyan

# --- 1. Squelette CodeIgniter 4 (Composer) -----------------------------------
if (-not $SkipComposer) {
    if (-not (Get-Command composer -ErrorAction SilentlyContinue)) {
        throw "Composer est introuvable dans le PATH. Installez-le : https://getcomposer.org/"
    }

    $Tmp = Join-Path $env:TEMP ('ci4_appstarter_' + [guid]::NewGuid().ToString('N'))
    Write-Host "Creation du projet CodeIgniter 4 dans $Tmp ..." -ForegroundColor Cyan
    & composer create-project codeigniter4/appstarter $Tmp --no-interaction --prefer-dist
    if ($LASTEXITCODE -ne 0) { throw "composer create-project a echoue (code $LASTEXITCODE)." }

    # Fichiers/dossiers du depot a NE PAS ecraser par appstarter
    $Keep = @(
        '.git', '.github', '.gitattributes',
        'conception', 'script', 'demo', 'demo.zip',
        'todo.md', 'RH.md', 'Readme.md'
    )

    Write-Host "Copie du squelette a la racine ..." -ForegroundColor Cyan
    Get-ChildItem -LiteralPath $Tmp -Force | ForEach-Object {
        if ($Keep -contains $_.Name) { return }
        $dest = Join-Path $Root $_.Name
        if (Test-Path -LiteralPath $dest) {
            Remove-Item -LiteralPath $dest -Recurse -Force
        }
        Copy-Item -LiteralPath $_.FullName -Destination $dest -Recurse -Force
    }
    Remove-Item -LiteralPath $Tmp -Recurse -Force

    # .env a partir du modele CI4
    $envModel = Join-Path $Root 'env'
    $envFile  = Join-Path $Root '.env'
    if ((Test-Path -LiteralPath $envModel) -and -not (Test-Path -LiteralPath $envFile)) {
        Copy-Item -LiteralPath $envModel -Destination $envFile
        Write-Host "Fichier .env cree a partir de 'env'." -ForegroundColor Yellow
    }
}

# --- 2. Arborescence metier ---------------------------------------------------
$Dirs = @(
    # Structure applicative CI4 (si appstarter non utilise)
    'app/Config', 'app/Controllers', 'app/Database', 'app/Database/Migrations',
    'app/Database/Seeds', 'app/Entities', 'app/Filters', 'app/Helpers',
    'app/Libraries', 'app/Models', 'app/Views',
    # Domaines (Piste A / B / C / D)
    'app/Controllers/Admin',
    'app/Controllers/Recrutement',
    'app/Controllers/Personnel',
    'app/Models/Admin',
    'app/Models/Recrutement',
    'app/Models/Personnel',
    'app/Entities/Admin',
    'app/Entities/Recrutement',
    'app/Entities/Personnel',
    'app/Libraries/Common',
    # Tests
    'tests/unit', 'tests/integration', 'tests/database',
    # Ressources front servies par public/assets/
    'public/assets',
    'public/assets/css',
    'public/assets/js',
    'public/assets/js/pages'
)

Write-Host "Creation de l'arborescence metier ..." -ForegroundColor Cyan
foreach ($d in $Dirs) {
    New-Item -ItemType Directory -Path (Join-Path $Root $d) -Force | Out-Null
}

# Placeholder .gitkeep pour conserver les dossiers vides sous Git
$Placeholders = @(
    'app/Controllers/Admin', 'app/Controllers/Recrutement', 'app/Controllers/Personnel',
    'app/Models/Admin', 'app/Models/Recrutement', 'app/Models/Personnel',
    'app/Entities/Admin', 'app/Entities/Recrutement', 'app/Entities/Personnel',
    'app/Libraries/Common', 'tests/unit', 'tests/integration', 'tests/database',
    'public/assets/css', 'public/assets/js/pages'
)
foreach ($p in $Placeholders) {
    $keep = Join-Path $Root (Join-Path $p '.gitkeep')
    if (-not (Test-Path -LiteralPath $keep)) {
        New-Item -ItemType File -Path $keep -Force | Out-Null
    }
}

Write-Host "Bootstrap CI4 termine." -ForegroundColor Green
Write-Host ("Structure creee sous : " + $Root) -ForegroundColor Green
