<?php

/**
 * Several models in this package (HrEmployee, HrEmployeeSalaryInfo,
 * HrEmployeeLogin) opt into mestiaque/audit's HasAudit trait and its
 * HasAuditParent/AuditableDisplay contracts. That package is an optional,
 * separately-installed dependency (see composer.json "suggest") — when it
 * isn't required by the consuming app, none of its classes are autoloadable
 * and referencing them would otherwise be a fatal "Trait/Interface not
 * found" error.
 *
 * Each model requires this file before it touches ME\Audit\*, so on-demand
 * we declare no-op stand-ins here only when the real ones aren't already
 * loaded. When mestiaque/audit *is* installed, trait_exists()/interface_exists()
 * find the real classes first and these declarations are skipped entirely.
 */

namespace ME\Audit\Traits {
    if (! trait_exists(HasAudit::class)) {
        trait HasAudit
        {
            public static function bootHasAudit(): void
            {
                // no-op: mestiaque/audit package is not installed.
            }

            public function getAuditInclude(): array
            {
                return $this->auditInclude ?? [];
            }

            public function getAuditExclude(): array
            {
                return $this->auditExclude ?? [];
            }

            public function getAuditRelations(): array
            {
                return $this->auditRelations ?? [];
            }

            public function getAuditLabelField(): ?string
            {
                return $this->auditLabel ?? null;
            }

            public function getAuditLabel(): ?string
            {
                return null;
            }
        }
    }
}

namespace ME\Audit\Contracts {
    if (! interface_exists(HasAuditParent::class)) {
        interface HasAuditParent
        {
            public function auditParent(): ?\Illuminate\Database\Eloquent\Model;
        }
    }

    if (! interface_exists(AuditableDisplay::class)) {
        interface AuditableDisplay
        {
            public function getAuditDisplayName(): string;
        }
    }
}
