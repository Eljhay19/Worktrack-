[CmdletBinding()]
param(
    [string]$PhpPath = 'C:\xampp\php\php.exe',
    [switch]$StartNow
)

$ErrorActionPreference = 'Stop'
$taskName = 'WORKTRACK Scheduler'
$projectRoot = Split-Path -Parent $PSScriptRoot
$artisanPath = Join-Path $projectRoot 'artisan'
$windowsPowerShell = Join-Path $env:SystemRoot 'System32\WindowsPowerShell\v1.0\powershell.exe'
foreach ($requiredFile in @($PhpPath, $artisanPath, $windowsPowerShell)) {
    if (-not (Test-Path -LiteralPath $requiredFile -PathType Leaf)) {
        throw "Required file was not found: $requiredFile"
    }
}

$existingTask = Get-ScheduledTask -TaskName $taskName -ErrorAction SilentlyContinue
if ($existingTask) {
    $ownedAction = @($existingTask.Actions | Where-Object {
        $_.Arguments -and $_.Arguments.IndexOf($artisanPath, [StringComparison]::OrdinalIgnoreCase) -ge 0
    })
    if ($ownedAction.Count -eq 0) {
        throw 'A task with this name already exists for another project. It was not changed.'
    }
}

$quotedPhp = "'" + $PhpPath.Replace("'", "''") + "'"
$quotedArtisan = "'" + $artisanPath.Replace("'", "''") + "'"
$runCommand = '& ' + $quotedPhp + ' ' + $quotedArtisan + ' schedule:work; exit $LASTEXITCODE'
$action = New-ScheduledTaskAction -Execute $windowsPowerShell `
    -Argument ('-NoProfile -NonInteractive -WindowStyle Hidden -Command "' + $runCommand + '"') `
    -WorkingDirectory $projectRoot
$trigger = New-ScheduledTaskTrigger -Once -At (Get-Date).AddMinutes(1) `
    -RepetitionInterval (New-TimeSpan -Minutes 1)
$settings = New-ScheduledTaskSettingsSet -Hidden -StartWhenAvailable `
    -MultipleInstances IgnoreNew -ExecutionTimeLimit ([TimeSpan]::Zero) -RestartCount 3 -RestartInterval (New-TimeSpan -Minutes 1) `
    -AllowStartIfOnBatteries -DontStopIfGoingOnBatteries
$identity = [Security.Principal.WindowsIdentity]::GetCurrent().Name
$principal = New-ScheduledTaskPrincipal -UserId $identity -LogonType Interactive -RunLevel Limited
$definition = New-ScheduledTask -Action $action -Trigger $trigger `
    -Settings $settings -Principal $principal `
    -Description 'Keep the WORKTRACK scheduler worker running for second-level reminder dispatch and encrypted backups.'
Register-ScheduledTask -TaskName $taskName -InputObject $definition -Force | Out-Null
if ($StartNow) {
    Start-ScheduledTask -TaskName $taskName
}
Write-Output 'WORKTRACK Scheduler installed: persistent worker with second-level dispatch while this Windows user is signed in.'
Get-ScheduledTaskInfo -TaskName $taskName | Select-Object LastRunTime, LastTaskResult, NextRunTime
