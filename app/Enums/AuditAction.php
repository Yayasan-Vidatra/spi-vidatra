<?php

namespace App\Enums;

/**
 * Jenis aksi yang dicatat di log audit (F-09, US-24).
 */
enum AuditAction: string
{
    case DemoRoleAssigned = 'akun.peran_demo_ditetapkan';
    case AssignmentCreated = 'penugasan.dibuat';
    case AssignmentClosed = 'penugasan.ditutup';
    case AssignmentReopened = 'penugasan.dibuka_kembali';
    case AuditorAssigned = 'penugasan.auditor_ditunjuk';
    case AuditorRevoked = 'penugasan.auditor_dicabut';
    case ConfidentialAccessChanged = 'penugasan.akses_rahasia_diubah';
    case FolderCreated = 'folder.dibuat';
    case FolderRenamed = 'folder.diubah_nama';
    case FolderMoved = 'folder.dipindah';
    case FolderArchived = 'folder.diarsipkan';
    case FolderLabelChanged = 'folder.label_diubah';
}
