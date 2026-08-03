<#
.SYNOPSIS
Creates a ZIGO DevOps ZIP from the files changed between two Git commits.

.DESCRIPTION
The generated deploy-manifest.json contains exactly these properties:
package_name, environment, branch, commit_hash, files, migrate, migrations,
and seeders. No generated_at or other metadata is emitted.

Each files item contains exactly path and sha256. Paths use forward slashes,
directory entries are omitted, hashes are lowercase, and the manifest never
lists itself. BaseCommit is used only to calculate the diff.
#>
[CmdletBinding()]
param(
    [Parameter(Mandatory = $true)]
    [ValidatePattern('^[a-fA-F0-9]{7,40}$')]
    [string] $BaseCommit,

    [Parameter(Mandatory = $true)]
    [ValidatePattern('^(HEAD|[a-fA-F0-9]{7,40})$')]
    [string] $TargetCommit,

    [Parameter(Mandatory = $true)]
    [ValidatePattern('^[A-Za-z0-9._-]{1,120}$')]
    [string] $PackageName,

    [ValidateSet('stage', 'production')]
    [string] $Environment = 'stage',

    [ValidatePattern('^[A-Za-z0-9._/-]{1,120}$')]
    [string] $Branch,

    [ValidatePattern('^[A-Za-z0-9_]+$')]
    [string[]] $Seeder = @(),

    [Parameter(Mandatory = $true)]
    [string] $OutputPath
)

$ErrorActionPreference = 'Stop'
$allowedRoots = @(
    'app/',
    'bootstrap/',
    'config/',
    'database/migrations/',
    'database/seeders/',
    'public/',
    'resources/',
    'routes/'
)
$blockedParts = @('.env', 'vendor', 'storage', 'node_modules', '.git')
$blockedExtensions = @(
    '.exe', '.com', '.bat', '.cmd', '.ps1', '.sh', '.bash', '.zsh',
    '.fish', '.phar', '.msi', '.dll', '.so'
)

function Invoke-GitChecked {
    param([Parameter(Mandatory = $true)][string[]] $Arguments)

    $output = & git @Arguments 2>&1
    if ($LASTEXITCODE -ne 0) {
        throw "Git no pudo completar la operación solicitada: $output"
    }

    return $output
}

function Assert-PortableAllowedPath {
    param([Parameter(Mandatory = $true)][string] $Path)

    if ($Path.Contains('\')) {
        throw "Ruta con separadores Windows no permitida: $Path"
    }
    if ($Path.StartsWith('/') -or $Path -match '^[A-Za-z]:') {
        throw "Ruta absoluta no permitida: $Path"
    }

    $parts = $Path.Split('/')
    if ($parts -contains '..' -or $parts -contains '.') {
        throw "Ruta relativa insegura: $Path"
    }
    foreach ($part in $parts) {
        $lower = $part.ToLowerInvariant()
        if ($blockedParts -contains $lower -or $lower.StartsWith('.env')) {
            throw "Ruta bloqueada: $Path"
        }
    }

    $allowed = $false
    foreach ($root in $allowedRoots) {
        if ($Path.StartsWith($root, [StringComparison]::Ordinal)) {
            $allowed = $true
            break
        }
    }
    if (-not $allowed) {
        throw "Ruta fuera de allowlist: $Path"
    }
    if ($blockedExtensions -contains [IO.Path]::GetExtension($Path).ToLowerInvariant()) {
        throw "Tipo de archivo bloqueado: $Path"
    }
}

function Test-AllowedRoot {
    param([Parameter(Mandatory = $true)][string] $Path)

    foreach ($root in $allowedRoots) {
        if ($Path.StartsWith($root, [StringComparison]::Ordinal)) {
            return $true
        }
    }

    return $false
}

Invoke-GitChecked @('cat-file', '-e', "$BaseCommit`^{commit}") | Out-Null
Invoke-GitChecked @('cat-file', '-e', "$TargetCommit`^{commit}") | Out-Null
$targetCommitHash = (Invoke-GitChecked @('rev-parse', '--verify', "$TargetCommit`^{commit}") | Select-Object -First 1).Trim().ToLowerInvariant()

$deletedFiles = @(Invoke-GitChecked @(
    'diff', '--name-only', '--diff-filter=D', $BaseCommit, $TargetCommit
)) | Where-Object { $_ -ne '' } | ForEach-Object {
    $_.Replace('\', '/')
} | Sort-Object -Unique
$allowedDeletedFiles = @($deletedFiles | Where-Object { Test-AllowedRoot $_ })
if ($allowedDeletedFiles.Count -gt 0) {
    throw 'El contrato MVP no permite eliminar archivos permitidos: ' + ($allowedDeletedFiles -join ', ')
}

$changedFiles = @(Invoke-GitChecked @(
    'diff', '--name-only', '--diff-filter=ACMRT', $BaseCommit, $TargetCommit
)) | Where-Object { $_ -ne '' } | ForEach-Object {
    $_.Replace('\', '/')
} | Sort-Object -Unique
$files = @($changedFiles | Where-Object { Test-AllowedRoot $_ })
$excludedFiles = @(
    $changedFiles | Where-Object { -not (Test-AllowedRoot $_) }
    $deletedFiles | Where-Object { -not (Test-AllowedRoot $_) }
) | Sort-Object -Unique

if ($files.Count -eq 0) {
    $excludedSummary = if ($excludedFiles.Count -gt 0) { $excludedFiles -join ', ' } else { '(ninguno)' }
    throw "No quedan archivos permitidos para empaquetar después del filtro. Archivos excluidos: $excludedSummary"
}
foreach ($file in $files) {
    Assert-PortableAllowedPath $file
}

$migrations = @($files | Where-Object {
    $_.StartsWith('database/migrations/', [StringComparison]::Ordinal) -and
    $_.EndsWith('.php', [StringComparison]::OrdinalIgnoreCase)
})

foreach ($seederName in $Seeder) {
    $seederPath = "database/seeders/$seederName.php"
    if ($files -notcontains $seederPath) {
        throw "El archivo del seeder declarado no está en el diff: $seederPath"
    }
}

Add-Type -AssemblyName System.IO.Compression
Add-Type -AssemblyName System.IO.Compression.FileSystem

$resolvedOutput = [IO.Path]::GetFullPath($OutputPath)
$outputDirectory = [IO.Path]::GetDirectoryName($resolvedOutput)
if (-not [IO.Directory]::Exists($outputDirectory)) {
    [IO.Directory]::CreateDirectory($outputDirectory) | Out-Null
}

$temporaryArchive = Join-Path ([IO.Path]::GetTempPath()) (
    'zigo-source-' + [Guid]::NewGuid().ToString('N') + '.zip'
)

try {
    $archiveArguments = @(
        'archive',
        '--format=zip',
        "--output=$temporaryArchive",
        $TargetCommit,
        '--'
    ) + $files
    Invoke-GitChecked $archiveArguments | Out-Null

    $sourceArchive = [IO.Compression.ZipFile]::OpenRead($temporaryArchive)
    try {
        $sourceEntries = @{}
        foreach ($entry in $sourceArchive.Entries) {
            if ($entry.Name -ne '') {
                $sourceEntries[$entry.FullName.Replace('\', '/')] = $entry
            }
        }

        $manifestFiles = @()
        foreach ($file in $files) {
            if (-not $sourceEntries.ContainsKey($file)) {
                throw "Git archive no contiene el archivo esperado: $file"
            }
            $stream = $sourceEntries[$file].Open()
            try {
                $sha = [Security.Cryptography.SHA256]::Create()
                try {
                    $hash = ([BitConverter]::ToString(
                        $sha.ComputeHash($stream)
                    )).Replace('-', '').ToLowerInvariant()
                } finally {
                    $sha.Dispose()
                }
            } finally {
                $stream.Dispose()
            }
            $manifestFiles += [ordered]@{
                path = $file
                sha256 = $hash
            }
        }

        $manifest = [ordered]@{
            package_name = $PackageName
            environment = $Environment
            branch = if ($Branch) { $Branch } else { $null }
            commit_hash = $targetCommitHash
            files = @($manifestFiles)
            migrate = ($migrations.Count -gt 0)
            migrations = @($migrations)
            seeders = @($Seeder)
        }

        if ([IO.File]::Exists($resolvedOutput)) {
            [IO.File]::Delete($resolvedOutput)
        }
        $outputStream = [IO.File]::Open(
            $resolvedOutput,
            [IO.FileMode]::CreateNew
        )
        try {
            $outputArchive = [IO.Compression.ZipArchive]::new(
                $outputStream,
                [IO.Compression.ZipArchiveMode]::Create,
                $false
            )
            try {
                foreach ($file in $files) {
                    $targetEntry = $outputArchive.CreateEntry(
                        $file,
                        [IO.Compression.CompressionLevel]::Optimal
                    )
                    $sourceStream = $sourceEntries[$file].Open()
                    $targetStream = $targetEntry.Open()
                    try {
                        $sourceStream.CopyTo($targetStream)
                    } finally {
                        $targetStream.Dispose()
                        $sourceStream.Dispose()
                    }
                }

                $manifestEntry = $outputArchive.CreateEntry(
                    'deploy-manifest.json',
                    [IO.Compression.CompressionLevel]::Optimal
                )
                $manifestStream = $manifestEntry.Open()
                $writer = [IO.StreamWriter]::new(
                    $manifestStream,
                    [Text.UTF8Encoding]::new($false)
                )
                try {
                    $writer.Write(
                        ($manifest | ConvertTo-Json -Depth 8)
                    )
                } finally {
                    $writer.Dispose()
                }
            } finally {
                $outputArchive.Dispose()
            }
        } finally {
            $outputStream.Dispose()
        }
    } finally {
        $sourceArchive.Dispose()
    }
} finally {
    if ([IO.File]::Exists($temporaryArchive)) {
        [IO.File]::Delete($temporaryArchive)
    }
}

Write-Output 'Archivos incluidos:'
$files | ForEach-Object { Write-Output "  - $_" }
Write-Output 'Archivos excluidos:'
if ($excludedFiles.Count -eq 0) {
    Write-Output '  - (ninguno)'
} else {
    $excludedFiles | ForEach-Object { Write-Output "  - $_" }
}
Write-Output "Ruta del ZIP: $resolvedOutput"
Write-Output "SHA-256: $((Get-FileHash -LiteralPath $resolvedOutput -Algorithm SHA256).Hash.ToLowerInvariant())"
Write-Output "Commit objetivo: $targetCommitHash"
