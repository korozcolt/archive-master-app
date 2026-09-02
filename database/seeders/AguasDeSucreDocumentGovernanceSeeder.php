<?php

namespace Database\Seeders;

use App\Enums\FinalDisposition;
use App\Models\Company;
use App\Models\Department;
use App\Models\DocumentarySeries;
use App\Models\DocumentarySubseries;
use App\Models\DocumentaryType;
use App\Models\RetentionSchedule;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AguasDeSucreDocumentGovernanceSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::query()->firstOrFail();

        $branch = $company->branches()->firstOrCreate(
            ['code' => 'PRINCIPAL'],
            [
                'name' => ['es' => 'Sede Principal'],
                'city' => 'Sincelejo',
                'state' => 'Sucre',
                'country' => 'Colombia',
                'active' => true,
            ],
        );

        DB::transaction(function () use ($company, $branch): void {
            foreach ($this->departmentDefinitions() as $definition) {
                $department = Department::query()->updateOrCreate(
                    [
                        'company_id' => $company->id,
                        'code' => $definition['code'],
                    ],
                    [
                        'branch_id' => $branch->id,
                        'name' => ['es' => $definition['name']],
                        'description' => ['es' => null],
                        'active' => true,
                    ],
                );

                $this->resetDepartmentGovernance($company->id, $department->id);
                $this->seedDepartmentGovernance($company->id, $department->id, $definition['series']);
            }

            Department::query()
                ->where('company_id', $company->id)
                ->where('code', 'OAJ160')
                ->update(['active' => false]);
        });
    }

    /**
     * @param  array<int, array<string, mixed>>  $seriesDefinitions
     */
    private function seedDepartmentGovernance(int $companyId, int $departmentId, array $seriesDefinitions): void
    {
        foreach ($seriesDefinitions as $seriesDefinition) {
            $series = DocumentarySeries::query()->create([
                'company_id' => $companyId,
                'department_id' => $departmentId,
                'code' => $seriesDefinition['code'],
                'name' => $seriesDefinition['name'],
                'description' => null,
                'is_active' => true,
            ]);

            foreach ($seriesDefinition['subseries'] as $subseriesDefinition) {
                $subseries = DocumentarySubseries::query()->create([
                    'company_id' => $companyId,
                    'department_id' => $departmentId,
                    'documentary_series_id' => $series->id,
                    'code' => $subseriesDefinition['code'],
                    'name' => $subseriesDefinition['name'],
                    'description' => null,
                    'is_active' => true,
                ]);

                $types = $subseriesDefinition['types'] ?? ['Expediente de '.$subseriesDefinition['name']];

                foreach (array_values($types) as $index => $typeName) {
                    DocumentaryType::query()->create([
                        'company_id' => $companyId,
                        'department_id' => $departmentId,
                        'documentary_subseries_id' => $subseries->id,
                        'code' => sprintf('TD%02d', $index + 1),
                        'name' => $typeName,
                        'description' => null,
                        'access_level_default' => 'interno',
                        'is_active' => true,
                    ]);
                }

                RetentionSchedule::query()->create([
                    'company_id' => $companyId,
                    'department_id' => $departmentId,
                    'documentary_subseries_id' => $subseries->id,
                    'documentary_type_id' => null,
                    'archive_phase' => 'gestion',
                    'management_years' => $subseriesDefinition['management_years'],
                    'central_years' => $subseriesDefinition['central_years'],
                    'historical_action' => $this->historicalAction($subseriesDefinition['final_disposition']),
                    'final_disposition' => $subseriesDefinition['final_disposition'],
                    'legal_basis' => 'TRD AGUAS DE SUCRE S.A. E.S.P. convalidada y remitida mediante comunicaciones internas SG110-119 a SG110-124 de 2023.',
                    'is_active' => true,
                ]);
            }
        }
    }

    private function resetDepartmentGovernance(int $companyId, int $departmentId): void
    {
        RetentionSchedule::query()
            ->where('company_id', $companyId)
            ->where('department_id', $departmentId)
            ->delete();

        DocumentaryType::query()
            ->where('company_id', $companyId)
            ->where('department_id', $departmentId)
            ->delete();

        DocumentarySubseries::query()
            ->where('company_id', $companyId)
            ->where('department_id', $departmentId)
            ->delete();

        DocumentarySeries::query()
            ->where('company_id', $companyId)
            ->where('department_id', $departmentId)
            ->delete();
    }

    private function historicalAction(FinalDisposition $finalDisposition): string
    {
        return match ($finalDisposition) {
            FinalDisposition::ConservacionTotal => 'Conservar de forma permanente conforme a la TRD oficial de AGUAS DE SUCRE.',
            FinalDisposition::Eliminacion => 'Eliminar al finalizar la retención primaria, dejando acta del Comité de Archivo.',
            FinalDisposition::Seleccion => 'Aplicar selección documental según la muestra definida en la TRD oficial.',
            FinalDisposition::Digitalizacion => 'Digitalizar para conservación y consulta conforme a la TRD oficial.',
            FinalDisposition::Microfilmacion => 'Microfilmar conforme a la TRD oficial.',
        };
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function departmentDefinitions(): array
    {
        return [
            [
                'code' => 'G100',
                'name' => 'GERENCIA',
                'series' => [
                    [
                        'code' => 'G100-2',
                        'name' => 'ACTAS',
                        'subseries' => [
                            $this->subseries('G100-2-01', 'Actas de Asamblea de Accionistas', 2, 10, FinalDisposition::ConservacionTotal, $this->actaTypes()),
                            $this->subseries('G100-2-08', 'Actas de Comité Directivo PDA', 2, 10, FinalDisposition::ConservacionTotal, $this->actaTypes(true)),
                            $this->subseries('G100-2-12', 'Actas de Junta Directiva', 2, 10, FinalDisposition::ConservacionTotal, $this->actaTypes()),
                        ],
                    ],
                    [
                        'code' => 'G100-3',
                        'name' => 'ACTOS ADMINISTRATIVOS',
                        'subseries' => [
                            $this->subseries('G100-3-01', 'Resoluciones', 2, 20, FinalDisposition::ConservacionTotal, $this->types('Resoluciones')),
                        ],
                    ],
                    [
                        'code' => 'G100-4',
                        'name' => 'CERTIFICADOS',
                        'subseries' => [
                            $this->subseries('G100-4-01', 'Certificados', 2, 0, FinalDisposition::Eliminacion, $this->types('Solicitud', 'Certificado')),
                        ],
                    ],
                    [
                        'code' => 'G100-5',
                        'name' => 'CIRCULARES',
                        'subseries' => [
                            $this->subseries('G100-5-01', 'Circulares', 2, 5, FinalDisposition::Seleccion, $this->types('Circular')),
                        ],
                    ],
                    [
                        'code' => 'G100-16',
                        'name' => 'INFORMES',
                        'subseries' => [
                            $this->subseries('G100-16-01', 'Informe a Entes de Control', 2, 10, FinalDisposition::ConservacionTotal, $this->informeTypes(['Anexos'])),
                            $this->subseries('G100-16-07', 'Informe de Gestión del Patrimonio Autónomo', 2, 10, FinalDisposition::ConservacionTotal, $this->informeTypes(['Anexos'])),
                        ],
                    ],
                    [
                        'code' => 'G100-24',
                        'name' => 'PLANES',
                        'subseries' => [
                            $this->subseries('G100-24-05', 'Plan Estratégico de Inversiones', 2, 10, FinalDisposition::ConservacionTotal, $this->types('Plan', 'Informe bimestral de avance', 'Informe de cierre', 'Anexos')),
                        ],
                    ],
                    [
                        'code' => 'G100-28',
                        'name' => 'REGISTROS',
                        'subseries' => [
                            $this->subseries('G100-28-04', 'Registro de Certificados de Disponibilidad de Recursos', 2, 10, FinalDisposition::Eliminacion, $this->types('CDR')),
                            $this->subseries('G100-28-05', 'Registro de Documentos de Constitución de la Empresa', 2, 20, FinalDisposition::ConservacionTotal, $this->types('Escrituras públicas de constitución', 'Inscripción en cámara de comercio', 'Inscripción en registro único tributario')),
                        ],
                    ],
                ],
            ],
            [
                'code' => 'SAF140',
                'name' => 'SUBGERENCIA ADMINISTRATIVA Y FINANCIERA',
                'series' => [
                    [
                        'code' => 'SAF140-2',
                        'name' => 'ACTAS',
                        'subseries' => [
                            $this->subseries('SAF140-2-02', 'Actas de Comité de Adquisiciones', 2, 10, FinalDisposition::ConservacionTotal, $this->actaTypes(true)),
                            $this->subseries('SAF140-2-04', 'Actas de Comité de Brigadistas', 2, 10, FinalDisposition::ConservacionTotal, $this->actaTypes(true)),
                            $this->subseries('SAF140-2-06', 'Actas de Comité de Convivencia Laboral', 2, 10, FinalDisposition::ConservacionTotal, $this->actaTypes(true)),
                            $this->subseries('SAF140-2-09', 'Actas de Comité Evaluador de Baja de Bienes', 2, 10, FinalDisposition::ConservacionTotal, $this->actaTypes(true)),
                            $this->subseries('SAF140-2-10', 'Actas de Comité Paritario de Seguridad y Salud en el Trabajo', 2, 10, FinalDisposition::ConservacionTotal, $this->actaTypes(true)),
                            $this->subseries('SAF140-2-11', 'Actas de Comité Técnico de Sostenibilidad Contable', 2, 10, FinalDisposition::ConservacionTotal, $this->actaTypes(true)),
                        ],
                    ],
                    [
                        'code' => 'SAF140-4',
                        'name' => 'CERTIFICADOS',
                        'subseries' => [
                            $this->subseries('SAF140-4-02', 'Certificados de Disponibilidad Presupuestal', 2, 10, FinalDisposition::Eliminacion, $this->types('CDP', 'Solicitud', 'Registro presupuestal')),
                            $this->subseries('SAF140-4-03', 'Certificados Laborales', 2, 0, FinalDisposition::Eliminacion, $this->types('Solicitud', 'Certificado')),
                        ],
                    ],
                    [
                        'code' => 'SAF140-5',
                        'name' => 'CIRCULARES',
                        'subseries' => [
                            $this->subseries('SAF140-5-01', 'Circulares', 2, 5, FinalDisposition::Seleccion, $this->types('Circular')),
                        ],
                    ],
                    [
                        'code' => 'SAF140-6',
                        'name' => 'COMPROBANTES CONTABLES',
                        'subseries' => [
                            $this->subseries('SAF140-6-01', 'Comprobantes de Egresos', 2, 10, FinalDisposition::Eliminacion, $this->types('Comprobante de egreso', 'Orden de pago', 'CDP', 'RP', 'Formato solicitud de pago', 'Cuenta de cobro o factura', 'Pago de seguridad social', 'Certificado de cuenta bancaria', 'Formato de reducción de retenciones', 'Soporte de pago de banco')),
                            $this->subseries('SAF140-6-02', 'Comprobantes de Ingreso', 2, 10, FinalDisposition::Eliminacion, $this->types('Comprobante de ingreso', 'Orden de pago FIA')),
                        ],
                    ],
                    [
                        'code' => 'SAF140-7',
                        'name' => 'CONCILIACIONES',
                        'subseries' => [
                            $this->subseries('SAF140-7-01', 'Conciliaciones Bancarias', 2, 10, FinalDisposition::Eliminacion, $this->types('Extracto bancario', 'Copia libros de banco', 'Conciliación bancaria')),
                        ],
                    ],
                    [
                        'code' => 'SAF140-12',
                        'name' => 'DECLARACIONES TRIBUTARIAS',
                        'subseries' => [
                            $this->subseries('SAF140-12-01', 'Declaración de Impuesto de Industria y Comercio', 2, 10, FinalDisposition::Eliminacion, $this->types('Declaración')),
                            $this->subseries('SAF140-12-02', 'Declaraciones de Renta y Complementarios', 2, 10, FinalDisposition::Eliminacion, $this->types('Declaración')),
                            $this->subseries('SAF140-12-03', 'Declaraciones de Retención en la Fuente', 2, 10, FinalDisposition::Eliminacion, $this->types('Declaración mensual')),
                            $this->subseries('SAF140-12-04', 'Declaración y Pago de Estampillas', 2, 10, FinalDisposition::Eliminacion, $this->types('Declaración y pago de estampillas')),
                        ],
                    ],
                    [
                        'code' => 'SAF140-14',
                        'name' => 'ESTADOS FINANCIEROS',
                        'subseries' => [
                            $this->subseries('SAF140-14-01', 'Estados Financieros', 2, 10, FinalDisposition::ConservacionTotal, $this->types('Balance general', 'Estado de resultados', 'Estado de cambios en el patrimonio', 'Estado de cambios en la situación financiera', 'Estado de flujo de efectivo', 'Estados financieros consolidados')),
                        ],
                    ],
                    [
                        'code' => 'SAF140-15',
                        'name' => 'HISTORIAS LABORALES',
                        'subseries' => [
                            $this->subseries('SAF140-15-01', 'Historias Laborales', 2, 80, FinalDisposition::ConservacionTotal, $this->types('Copia acto administrativo de nombramiento', 'Oficio de notificación de nombramiento', 'Documentos de identificación', 'Hoja de vida formato único función pública', 'Copia de documento de identidad', 'Copia de tarjeta profesional', 'Copias de diplomas', 'Copias de certificación de estudios', 'Certificaciones de experiencia', 'Antecedentes disciplinarios', 'Declaración de bienes y renta', 'Certificado de aptitud laboral', 'Certificados de afiliación', 'Copias de actos administrativos de situaciones administrativas', 'Evaluación de desempeño', 'Acto administrativo de retiro o desvinculación')),
                        ],
                    ],
                    [
                        'code' => 'SAF140-16',
                        'name' => 'INFORMES',
                        'subseries' => [
                            $this->subseries('SAF140-16-02', 'Informe Contable Público', 2, 10, FinalDisposition::ConservacionTotal, $this->informeTypes(['Anexos'])),
                            $this->subseries('SAF140-16-03', 'Informe de Categoría Única de Información del Presupuesto Ordinario', 2, 10, FinalDisposition::ConservacionTotal, $this->informeTypes(['Anexos'])),
                            $this->subseries('SAF140-16-12', 'Informe Sistema General de Regalías', 2, 10, FinalDisposition::ConservacionTotal, $this->informeTypes(['Anexos'])),
                        ],
                    ],
                    [
                        'code' => 'SAF140-18',
                        'name' => 'INVENTARIOS',
                        'subseries' => [
                            $this->subseries('SAF140-18-01', 'Inventario de Bienes de Consumo', 2, 10, FinalDisposition::Eliminacion, $this->types('Inventario de bienes', 'Anexos')),
                            $this->subseries('SAF140-18-02', 'Inventarios de Propiedad Planta y Equipo', 2, 10, FinalDisposition::Eliminacion, $this->types('Inventario anual', 'Acta general de inventario')),
                        ],
                    ],
                    [
                        'code' => 'SAF140-20',
                        'name' => 'LIBROS CONTABLES AUXILIARES',
                        'subseries' => [
                            $this->subseries('SAF140-20-01', 'Libro Auxiliar', 2, 10, FinalDisposition::ConservacionTotal, $this->types('Libro auxiliar de contabilidad')),
                        ],
                    ],
                    [
                        'code' => 'SAF140-21',
                        'name' => 'LIBROS CONTABLES PRINCIPALES',
                        'subseries' => [
                            $this->subseries('SAF140-21-01', 'Libro Diario', 2, 10, FinalDisposition::ConservacionTotal, $this->types('Libro diario de contabilidad')),
                            $this->subseries('SAF140-21-02', 'Libro Mayor y Balance', 2, 10, FinalDisposition::ConservacionTotal, $this->types('Libro mayor')),
                        ],
                    ],
                    [
                        'code' => 'SAF140-22',
                        'name' => 'MANUALES',
                        'subseries' => [
                            $this->subseries('SAF140-22-04', 'Manual de Funciones y Reglamento Interno de Trabajo', 2, 10, FinalDisposition::ConservacionTotal, $this->types('Manual', 'Reglamento interno de trabajo')),
                            $this->subseries('SAF140-22-05', 'Manual de Obligaciones y Responsabilidades Dentro de SGSST', 2, 10, FinalDisposition::ConservacionTotal, $this->types('Manual')),
                            $this->subseries('SAF140-22-06', 'Manual de Políticas Contables', 2, 10, FinalDisposition::ConservacionTotal, $this->types('Manual')),
                            $this->subseries('SAF140-22-08', 'Manual de Procedimientos Contables', 2, 10, FinalDisposition::ConservacionTotal, $this->types('Guía para el área de contabilidad', 'Guía para la preparación y presentación de la información contable', 'Guía para el seguimiento a observaciones contables')),
                            $this->subseries('SAF140-22-09', 'Manual del Sistema de Gestión de Seguridad y Salud en el Trabajo', 2, 10, FinalDisposition::ConservacionTotal, $this->types('Manual', 'Reglamento de higiene y seguridad industrial')),
                            $this->subseries('SAF140-22-10', 'Manual para la Mejora Continua de SGSST', 2, 10, FinalDisposition::ConservacionTotal, $this->types('Manual')),
                        ],
                    ],
                    [
                        'code' => 'SAF140-23',
                        'name' => 'NÓMINA',
                        'subseries' => [
                            $this->subseries('SAF140-23-01', 'Nómina', 2, 80, FinalDisposition::ConservacionTotal, $this->types('Solicitud de CDP', 'CDP', 'Certificado de registro presupuestal', 'Nómina', 'Relación de descuentos de salud, pensión, parafiscales y cesantías')),
                        ],
                    ],
                    [
                        'code' => 'SAF140-24',
                        'name' => 'PLANES',
                        'subseries' => [
                            $this->subseries('SAF140-24-03', 'Plan Anual de Adquisiciones', 2, 10, FinalDisposition::ConservacionTotal, $this->types('Plan', 'Anexos')),
                            $this->subseries('SAF140-24-06', 'Plan de Aplicación del Protocolo de Bioseguridad', 2, 10, FinalDisposition::ConservacionTotal, $this->types('Plan', 'Anexos')),
                            $this->subseries('SAF140-24-08', 'Plan de Emergencias y Contingencias', 2, 10, FinalDisposition::ConservacionTotal, $this->types('Plan', 'Matriz de identificación de peligros y valoración de riesgos')),
                            $this->subseries('SAF140-24-11', 'Plan de Trabajo Anual de SGSST', 2, 10, FinalDisposition::ConservacionTotal, $this->types('Plan', 'Matriz de ausentismo')),
                        ],
                    ],
                    [
                        'code' => 'SAF140-27',
                        'name' => 'PROGRAMAS',
                        'subseries' => [
                            $this->subseries('SAF140-27-01', 'Programa de Bienestar Laboral', 2, 10, FinalDisposition::ConservacionTotal, $this->types('Programa', 'Anexos')),
                            $this->subseries('SAF140-27-02', 'Programa de Seguridad Vial', 2, 10, FinalDisposition::ConservacionTotal, $this->types('Programa', 'Anexos')),
                            $this->subseries('SAF140-27-03', 'Programa General de Mantenimiento', 2, 10, FinalDisposition::ConservacionTotal, $this->types('Programa', 'Anexos')),
                        ],
                    ],
                    [
                        'code' => 'SAF140-28',
                        'name' => 'REGISTROS',
                        'subseries' => [
                            $this->subseries('SAF140-28-04', 'Registro de Conciliaciones Interáreas', 2, 10, FinalDisposition::Eliminacion, $this->types('Actas de conciliación interáreas')),
                            $this->subseries('SAF140-28-06', 'Registro de Ejecuciones Presupuestales de Ingresos y Gastos', 2, 10, FinalDisposition::Eliminacion, $this->types('Ejecuciones presupuestales de ingresos y gastos')),
                            $this->subseries('SAF140-28-07', 'Registro de Indicadores de SST', 2, 10, FinalDisposition::Eliminacion, $this->types('Indicadores de SST')),
                            $this->subseries('SAF140-28-09', 'Registro de Movimientos Presupuestales', 2, 10, FinalDisposition::Eliminacion, $this->types('Adiciones', 'Reducciones', 'Traslados presupuestales', 'Liberaciones de compromisos')),
                            $this->subseries('SAF140-28-10', 'Registro de Notas y Ajustes Contables', 2, 10, FinalDisposition::Eliminacion, $this->types('Comprobantes de ajustes', 'Comprobantes de amortización', 'Cuentas por cobrar', 'Anexos', 'Depreciación acumulada', 'Notas débito', 'Notas crédito', 'Comprobantes cuentas de orden', 'Comprobantes de cierre')),
                            $this->subseries('SAF140-28-11', 'Registro de Supervisión de Contratos', 2, 0, FinalDisposition::Eliminacion, $this->types('Informe de actividades', 'Certificados de satisfacción', 'Informes de supervisión')),
                        ],
                    ],
                ],
            ],
            [
                'code' => 'ST130',
                'name' => 'SUBGERENCIA TÉCNICA',
                'series' => [
                    [
                        'code' => 'ST130-5',
                        'name' => 'CIRCULARES',
                        'subseries' => [
                            $this->subseries('ST130-5-01', 'Circulares', 2, 5, FinalDisposition::Seleccion, $this->types('Circular')),
                        ],
                    ],
                    [
                        'code' => 'ST130-16',
                        'name' => 'INFORMES',
                        'subseries' => [
                            $this->subseries('ST130-16-06', 'Informe de Gestión', 2, 10, FinalDisposition::ConservacionTotal, $this->types('Informe', 'Anexos')),
                        ],
                    ],
                    [
                        'code' => 'ST130-24',
                        'name' => 'PLANES',
                        'subseries' => [
                            $this->subseries('ST130-24-01', 'Plan Ambiental', 2, 10, FinalDisposition::ConservacionTotal, $this->types('Plan', 'Anexos de ejecución del plan ambiental')),
                            $this->subseries('ST130-24-09', 'Plan de Gestión Predial', 2, 10, FinalDisposition::ConservacionTotal, $this->types('Plan', 'Anexos de ejecución del plan de gestión predial', 'Diseño del proyecto', 'Ficha predial', 'Estudio de título de propiedad', 'Avalúo del predio', 'Oferta de compra', 'Solicitud de licencias', 'Licencias ambientales', 'Licencias de subdivisión y planeación', 'Escrituras públicas de los predios', 'Certificados de tradición y libertad', 'Certificados paz y salvo de impuesto predial', 'Permisos de las entidades públicas')),
                        ],
                    ],
                    [
                        'code' => 'ST130-28',
                        'name' => 'REGISTROS',
                        'subseries' => [
                            $this->subseries('ST130-28-11', 'Registro de Supervisión de Contratos', 2, 0, FinalDisposition::Eliminacion, $this->types('Certificados de satisfacción', 'Informes de actividades', 'Anexos')),
                        ],
                    ],
                ],
            ],
            [
                'code' => 'SAP150',
                'name' => 'SUBGERENCIA DE ASEGURAMIENTO DE LA PRESTACIÓN DEL SERVICIO',
                'series' => [
                    [
                        'code' => 'SAP150-5',
                        'name' => 'CIRCULARES',
                        'subseries' => [
                            $this->subseries('SAP150-5-01', 'Circulares', 2, 5, FinalDisposition::Seleccion, $this->types('Circular')),
                        ],
                    ],
                    [
                        'code' => 'SAP150-16',
                        'name' => 'INFORMES',
                        'subseries' => [
                            $this->subseries('SAP150-16-08', 'Informe de Rendición de Cuentas', 2, 10, FinalDisposition::ConservacionTotal, $this->types('Informe', 'Anexos')),
                        ],
                    ],
                    [
                        'code' => 'SAP150-24',
                        'name' => 'PLANES',
                        'subseries' => [
                            $this->subseries('SAP150-24-02', 'Plan Anticorrupción y Atención al Ciudadano', 2, 10, FinalDisposition::ConservacionTotal, $this->types('Plan', 'Anexos ejecución del plan')),
                            $this->subseries('SAP150-24-07', 'Plan de Aseguramiento de la Prestación del Servicio', 2, 10, FinalDisposition::ConservacionTotal, $this->types('Plan', 'Informe de seguimiento al plan de aseguramiento', 'Anexos')),
                            $this->subseries('SAP150-24-10', 'Plan de Gestión Social', 2, 10, FinalDisposition::ConservacionTotal, $this->types('Plan', 'Informe bimestral de plan de gestión social', 'Anexos')),
                        ],
                    ],
                    [
                        'code' => 'SAP150-25',
                        'name' => 'PQRS',
                        'subseries' => [
                            $this->subseries('SAP150-25-01', 'PQRS', 2, 10, FinalDisposition::ConservacionTotal, $this->types('Peticiones', 'Quejas', 'Reclamos', 'Sugerencias', 'Informe de PQRS')),
                        ],
                    ],
                    [
                        'code' => 'SAP150-28',
                        'name' => 'REGISTROS',
                        'subseries' => [
                            $this->subseries('SAP150-28-11', 'Registro de Supervisión de Contratos', 2, 0, FinalDisposition::Eliminacion, $this->types('Informe de actividades', 'Certificados de satisfacción', 'Informes de supervisión')),
                        ],
                    ],
                ],
            ],
            [
                'code' => 'CAI120',
                'name' => 'CONTROL Y AUDITORÍA INTERNA',
                'series' => [
                    [
                        'code' => 'CAI120-2',
                        'name' => 'ACTAS',
                        'subseries' => [
                            $this->subseries('CAI120-2-07', 'Actas de Comité de Coordinación de Control Interno', 2, 10, FinalDisposition::ConservacionTotal, $this->actaTypes(true)),
                        ],
                    ],
                    [
                        'code' => 'CAI120-16',
                        'name' => 'INFORMES',
                        'subseries' => [
                            $this->subseries('CAI120-16-04', 'Informe de Control Interno Contable', 2, 10, FinalDisposition::ConservacionTotal, $this->types('Informe', 'Anexos')),
                            $this->subseries('CAI120-16-05', 'Informe de Derechos de Autor', 2, 10, FinalDisposition::ConservacionTotal, $this->types('Informe', 'Constancia de envío')),
                            $this->subseries('CAI120-16-09', 'Informe de Seguimiento al Plan Anticorrupción y Atención al Ciudadano', 2, 10, FinalDisposition::ConservacionTotal, $this->types('Informe', 'Constancia de envío')),
                            $this->subseries('CAI120-16-10', 'Informe de Seguimiento de PQRS', 2, 10, FinalDisposition::ConservacionTotal, $this->types('Informe', 'Anexos')),
                            $this->subseries('CAI120-16-11', 'Informe del Sistema de Control Interno', 2, 10, FinalDisposition::ConservacionTotal, $this->types('Informe', 'Anexos')),
                        ],
                    ],
                    [
                        'code' => 'CAI120-22',
                        'name' => 'MANUALES',
                        'subseries' => [
                            $this->subseries('CAI120-22-02', 'Manual de Auditoría', 2, 10, FinalDisposition::ConservacionTotal, $this->types('Manual')),
                        ],
                    ],
                    [
                        'code' => 'CAI120-24',
                        'name' => 'PLANES',
                        'subseries' => [
                            $this->subseries('CAI120-24-04', 'Plan Anual de Auditoría', 2, 10, FinalDisposition::ConservacionTotal, $this->types('Plan', 'Informe de seguimiento a planes de mejoramiento de auditorías')),
                        ],
                    ],
                    [
                        'code' => 'CAI120-28',
                        'name' => 'REGISTROS',
                        'subseries' => [
                            $this->subseries('CAI120-28-01', 'Registro de Auditorías Externas', 2, 10, FinalDisposition::ConservacionTotal, $this->types('Copias de oficios recibidos', 'Informe de auditoría del ente de control externo')),
                            $this->subseries('CAI120-28-02', 'Registro de Auditorías Internas', 2, 10, FinalDisposition::ConservacionTotal, $this->types('Solicitud', 'Respuesta', 'Anexo plan de mejoramiento por proceso')),
                        ],
                    ],
                ],
            ],
            [
                'code' => 'SG110',
                'name' => 'SECRETARÍA GENERAL',
                'series' => [
                    [
                        'code' => 'SG110-1',
                        'name' => 'ACCIONES CONSTITUCIONALES',
                        'subseries' => [
                            $this->subseries('SG110-1-01', 'Acciones de Grupo', 2, 10, FinalDisposition::ConservacionTotal, $this->types('Traslado de acción de grupo', 'Auto de admisión', 'Oficio de notificación', 'Contestación', 'Sentencia', 'Oficio de notificación de sentencia de segunda instancia')),
                            $this->subseries('SG110-1-02', 'Acciones de Tutela', 2, 10, FinalDisposition::ConservacionTotal, $this->types('Traslado de acción de tutela', 'Auto de admisión', 'Oficio de notificación', 'Contestación', 'Oficio de notificación de sentencia', 'Sustentación de apelación', 'Sentencia de segunda instancia', 'Oficio de notificación de sentencia de segunda instancia')),
                        ],
                    ],
                    [
                        'code' => 'SG110-2',
                        'name' => 'ACTAS',
                        'subseries' => [
                            $this->subseries('SG110-2-03', 'Actas de Comité de Archivo', 2, 10, FinalDisposition::ConservacionTotal, $this->actaTypes(true)),
                            $this->subseries('SG110-2-05', 'Actas de Comité de Conciliación y Defensa Judicial', 2, 10, FinalDisposition::ConservacionTotal, $this->actaTypes(true)),
                        ],
                    ],
                    [
                        'code' => 'SG110-4',
                        'name' => 'CERTIFICADOS',
                        'subseries' => [
                            $this->subseries('SG110-4-01', 'Certificados', 2, 0, FinalDisposition::Eliminacion, $this->types('Solicitud', 'Certificado')),
                        ],
                    ],
                    [
                        'code' => 'SG110-5',
                        'name' => 'CIRCULARES',
                        'subseries' => [
                            $this->subseries('SG110-5-01', 'Circulares', 2, 5, FinalDisposition::Seleccion, $this->types('Circular')),
                        ],
                    ],
                    [
                        'code' => 'SG110-7',
                        'name' => 'CONCEPTOS',
                        'subseries' => [
                            $this->subseries('SG110-7-01', 'Concepto Jurídico', 2, 5, FinalDisposition::Seleccion, $this->types('Solicitud de concepto', 'Concepto')),
                        ],
                    ],
                    [
                        'code' => 'SG110-9',
                        'name' => 'CONSECUTIVO DE COMUNICACIONES OFICIALES',
                        'subseries' => [
                            $this->subseries('SG110-9-01', 'Consecutivos de Comunicaciones Oficiales Enviadas', 2, 5, FinalDisposition::Eliminacion, $this->types('Acta de cierre anual de consecutivos', 'Listado de consecutivos anulados')),
                            $this->subseries('SG110-9-02', 'Consecutivos de Comunicaciones Oficiales Recibidas', 2, 5, FinalDisposition::Eliminacion, $this->types('Acta de cierre anual de consecutivos', 'Listado de números anulados')),
                        ],
                    ],
                    [
                        'code' => 'SG110-10',
                        'name' => 'CONTRATOS',
                        'subseries' => [
                            $this->subseries('SG110-10-01', 'Contrato de Arrendamiento', 2, 20, FinalDisposition::Seleccion, $this->types('Análisis del sector', 'Estudio de conveniencia', 'Cotización', 'Solicitud de CDP', 'CDP', 'Invitación', 'Documentos del arrendador', 'Certificados de antecedentes', 'Paz y salvo sistema seguridad social', 'Documentos relacionados del bien', 'Contrato', 'Registro presupuestal', 'Informes de supervisión', 'Comprobantes de egresos de pagos', 'Acta final')),
                            $this->subseries('SG110-10-02', 'Contrato de Compraventa', 2, 20, FinalDisposition::Seleccion, $this->types('Análisis del sector', 'Estudio de conveniencia', 'Cotización', 'Solicitud de CDP', 'CDP', 'Invitación', 'Registro mercantil o certificado de existencia y representación legal', 'Inscripción en cámara de comercio', 'Copia de documento de identidad', 'Certificados de antecedentes', 'Balance financiero', 'Declaración de bienes y rentas', 'Paz y salvo sistema seguridad social', 'Experiencias en venta del producto', 'Contrato', 'Registro presupuestal', 'Resolución aprobación de póliza', 'Acta de inicio', 'Informes de supervisión', 'Comprobantes de egresos de pagos', 'Acta final')),
                            $this->subseries('SG110-10-03', 'Contrato de Consultoría', 2, 20, FinalDisposition::ConservacionTotal, $this->types('Análisis del sector', 'Estudio de conveniencia', 'Solicitud de CDP', 'CDP', 'Invitación pública', 'Capacidad experiencia', 'Capacidad jurídica', 'Oferta económica', 'Comunicación de aceptación', 'Registro presupuestal', 'Póliza', 'Resolución de aprobación de póliza', 'Acta de inicio', 'Publicación SECOP I', 'Informes de supervisión', 'Comprobantes de egresos de pagos')),
                            $this->subseries('SG110-10-04', 'Contrato de Interventoría', 2, 20, FinalDisposition::ConservacionTotal, $this->types('Certificado disponibilidad de recursos', 'Análisis del sector', 'Estudio de conveniencia', 'Proyecto de pliego de condiciones', 'Aviso de licitación o convocatoria', 'Resolución de apertura', 'Observaciones al proyecto pliego', 'Respuesta a las observaciones', 'Pliego de condiciones definitivo', 'Acta de cierre de proceso', 'Verificación de requisitos habilitantes', 'Acta de revisión', 'Acta de audiencia de adjudicación', 'Resolución de adjudicación', 'Contrato', 'Póliza', 'Resolución aprobación de póliza', 'Acta de inicio', 'Pólizas actualizadas', 'Acta modificatoria', 'Acta parcial de recibo', 'Actas de suspensión o prórrogas', 'Acta de reinicio de obra', 'Informes de supervisión', 'Comprobantes de egresos de pagos', 'Acta final')),
                            $this->subseries('SG110-10-05', 'Contrato de Obra', 2, 20, FinalDisposition::Seleccion, $this->types('Certificado disponibilidad de recursos', 'Análisis del sector', 'Estudio de conveniencia', 'Proyecto de pliego de condiciones', 'Aviso de licitación o convocatoria', 'Anexo técnico', 'Cronograma', 'Glosario', 'Pacto de transparencia', 'Minuta del contrato', 'Carta de presentación', 'Conformación de proponentes', 'Capacidad financiera organizacional', 'Capacidad residual', 'Pago de seguridad social y parafiscal', 'Factor de calidad', 'Guía para programación de obra', 'Vinculación de personas con discapacidad', 'Puntaje de industria nacional', 'Presupuesto oficial', 'Matriz indicador organizacional', 'Matriz de riesgos', 'Observaciones al proyecto pliego de condiciones', 'Respuesta a observaciones al proyecto pliego de condiciones', 'Resolución de apertura', 'Pliegos definitivos', 'Adendas', 'Acta de diligencia de cierre de proceso', 'Informe preliminar de evaluación técnica, jurídica y económica', 'Observación al informe preliminar de evaluación', 'Informe de evaluación', 'Verificación de requisitos habilitantes', 'Manual y link para audiencia de adjudicación', 'Link para continuar audiencia de adjudicación', 'Acta de audiencia de adjudicación', 'Resolución de adjudicación', 'Contrato', 'Pólizas', 'Resolución de aprobación de póliza', 'Acta de inicio', 'Registro único tributario', 'Anticipo', 'Acta modificatoria', 'Acta parcial', 'Acta de suspensión o prórroga', 'Acta de reinicio de suspensión', 'Acta de entrega de obra', 'Acta final', 'Acta de liquidación de contrato')),
                            $this->subseries('SG110-10-06', 'Contrato de Prestación de Servicios', 2, 20, FinalDisposition::Seleccion, $this->types('Análisis del sector', 'Estudio de conveniencia', 'Cotización', 'Solicitud de CDP', 'CDP', 'Invitación', 'Propuesta', 'Registro mercantil o certificado de existencia y representación legal', 'Inscripción en cámara de comercio', 'Copia de documento de identidad', 'Certificados de antecedentes', 'Copia RUT', 'Balance financiero', 'Declaración de bienes y rentas', 'Paz y salvo sistema seguridad social', 'Experiencias en venta del servicio', 'Contrato', 'Registro presupuestal', 'Póliza y resolución de aprobación', 'Acta de inicio', 'Informes de supervisión', 'Comprobantes de egresos de pagos')),
                            $this->subseries('SG110-10-07', 'Contrato de Prestación de Servicios Profesionales', 2, 20, FinalDisposition::Seleccion, $this->types('Análisis del sector', 'Estudio de conveniencia', 'Solicitud de CDP', 'CDP', 'Invitación', 'Propuesta', 'Hoja de vida', 'Copia de documento de identidad', 'Copia de tarjeta profesional', 'Copia de diploma bachiller', 'Copia de títulos de pregrado', 'Copia de títulos de posgrado', 'Certificados de educación informal', 'Certificados de experiencia', 'Antecedentes judiciales', 'Certificado SRNMC', 'Antecedentes fiscales', 'Antecedentes disciplinarios', 'Antecedentes de consejos profesionales', 'Copia RUT expedido por DIAN', 'Declaración de bienes y rentas', 'Certificado de aportes y afiliación al sistema de seguridad social', 'Certificado de examen ocupacional de ingreso', 'Certificado de afiliación de ARL', 'Notificación de aceptación de oferta', 'Informes de actividades', 'Informes de supervisión', 'Comprobantes de egresos de pagos')),
                            $this->subseries('SG110-10-08', 'Contrato de Suministro', 2, 20, FinalDisposition::Seleccion, $this->types('Análisis del sector', 'Estudio de conveniencia', 'Solicitud de CDP', 'CDP', 'Invitación', 'Propuesta', 'Registro mercantil o certificado de existencia y representación legal', 'Inscripción en cámara de comercio', 'Copia de documento de identidad', 'Certificados de antecedentes', 'Balance financiero', 'Declaración de bienes y rentas', 'Paz y salvo sistema seguridad social', 'Experiencias en venta de productos', 'Contrato', 'Registro presupuestal', 'Póliza y resolución de aprobación', 'Informes de supervisión', 'Comprobantes de egresos de pagos')),
                        ],
                    ],
                    [
                        'code' => 'SG110-11',
                        'name' => 'CONVENIOS',
                        'subseries' => [
                            $this->subseries('SG110-11-01', 'Convenios Interinstitucionales', 2, 20, FinalDisposition::ConservacionTotal, $this->types('Estudios previos', 'Solicitud de elaboración de convenio', 'CDP', 'Hojas de vida función pública', 'Informe de actividades del convenio', 'Solicitud de adición o prórroga del convenio', 'Acta de finalización de convenio')),
                        ],
                    ],
                    [
                        'code' => 'SG110-13',
                        'name' => 'DERECHOS DE PETICIÓN',
                        'subseries' => [
                            $this->subseries('SG110-13-01', 'Derechos de Petición', 2, 10, FinalDisposition::Seleccion, $this->types('Derecho de petición', 'Respuesta derecho de petición')),
                        ],
                    ],
                    [
                        'code' => 'SG110-16',
                        'name' => 'INFORMES',
                        'subseries' => [
                            $this->subseries('SG110-16-06', 'Informe de Gestión', 2, 10, FinalDisposition::ConservacionTotal, $this->types('Solicitud de informe', 'Informe')),
                        ],
                    ],
                    [
                        'code' => 'SG110-17',
                        'name' => 'INSTRUMENTOS ARCHIVÍSTICOS',
                        'subseries' => [
                            $this->subseries('SG110-17-01', 'Banco Terminológico de Series y Subseries Documentales', 2, 10, FinalDisposition::ConservacionTotal, $this->types('Banco terminológico')),
                            $this->subseries('SG110-17-02', 'Cuadro de Clasificación Documental', 2, 10, FinalDisposition::ConservacionTotal, $this->types('Cuadro de clasificación documental')),
                            $this->subseries('SG110-17-03', 'Inventario Documental de Archivo Central', 2, 10, FinalDisposition::ConservacionTotal, $this->types('Inventario documental')),
                            $this->subseries('SG110-17-05', 'Plan Institucional de Archivos', 2, 10, FinalDisposition::ConservacionTotal, $this->types('Plan institucional de archivos')),
                            $this->subseries('SG110-17-06', 'Programa de Gestión Documental', 2, 10, FinalDisposition::ConservacionTotal, $this->types('Programa de gestión documental', 'Acto administrativo de aprobación')),
                            $this->subseries('SG110-17-08', 'Tablas de Retención Documental', 2, 10, FinalDisposition::ConservacionTotal, $this->types('Acta comité evaluador de documentos', 'Certificado convalidación de TRD', 'Metodología de implementación', 'Registro de publicación', 'Certificado de inscripción en RUSD')),
                        ],
                    ],
                    [
                        'code' => 'SG110-18',
                        'name' => 'INSTRUMENTOS DE CONTROL',
                        'subseries' => [
                            $this->subseries('SG110-18-01', 'Instrumento de Control de Comunicaciones Oficiales Enviadas', 2, 5, FinalDisposition::Eliminacion, $this->types('Libro de control de comunicaciones oficiales enviadas')),
                            $this->subseries('SG110-18-02', 'Instrumento de Control de Comunicaciones Oficiales Recibidas', 2, 5, FinalDisposition::Eliminacion, $this->types('Libro de control de comunicaciones oficiales recibidas')),
                        ],
                    ],
                    [
                        'code' => 'SG110-22',
                        'name' => 'MANUALES',
                        'subseries' => [
                            $this->subseries('SG110-22-01', 'Manual de Archivo y Transferencias Documentales', 2, 10, FinalDisposition::ConservacionTotal, $this->types('Manual')),
                            $this->subseries('SG110-22-03', 'Manual de Contratación', 2, 10, FinalDisposition::ConservacionTotal, $this->types('Manual')),
                            $this->subseries('SG110-22-07', 'Manual de Procedimiento de Comunicaciones Oficiales y Correspondencia', 2, 10, FinalDisposition::ConservacionTotal, $this->types('Manual')),
                        ],
                    ],
                    [
                        'code' => 'SG110-26',
                        'name' => 'PROCESOS JURÍDICOS',
                        'subseries' => [
                            $this->subseries('SG110-26-01', 'Procesos Contenciosos Administrativos', 2, 10, FinalDisposition::ConservacionTotal, $this->types('Auto inhibitorio', 'Auto de apertura', 'Citación de notificación', 'Edicto', 'Práctica de pruebas ordenadas', 'Recursos de apelación', 'Auto de investigación', 'Auto de prórroga', 'Auto de pliego de cargos', 'Auto de pruebas', 'Alegatos de conclusión', 'Fallo de segunda instancia', 'Antecedentes disciplinarios', 'Resolución')),
                            $this->subseries('SG110-26-02', 'Procesos Disciplinarios', 2, 10, FinalDisposition::ConservacionTotal, $this->types('Queja o informe', 'Auto inhibitorio', 'Auto de apertura', 'Citación de notificación', 'Edicto', 'Práctica de pruebas ordenadas', 'Auto de pliego de cargos', 'Auto de archivo', 'Defensor de oficio', 'Auto de pruebas', 'Recurso', 'Alegatos de conclusión', 'Fallo de primera instancia', 'Recurso proceso disciplinario', 'Fallo de segunda instancia', 'Antecedentes disciplinarios')),
                        ],
                    ],
                    [
                        'code' => 'SG110-28',
                        'name' => 'REGISTROS',
                        'subseries' => [
                            $this->subseries('SG110-28-11', 'Registro de Supervisión de Contratos', 2, 0, FinalDisposition::Eliminacion, $this->types('Informe de actividades', 'Certificados de satisfacción', 'Informes de supervisión')),
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function subseries(
        string $code,
        string $name,
        int $managementYears,
        int $centralYears,
        FinalDisposition $finalDisposition,
        array $types = [],
    ): array {
        return [
            'code' => $code,
            'name' => $name,
            'management_years' => $managementYears,
            'central_years' => $centralYears,
            'final_disposition' => $finalDisposition,
            'types' => $types,
        ];
    }

    /**
     * @return array<int, string>
     */
    private function types(string ...$types): array
    {
        return $types;
    }

    /**
     * @return array<int, string>
     */
    private function actaTypes(bool $includeCreationAct = false): array
    {
        $types = ['Citación', 'Acta', 'Anexos'];

        if ($includeCreationAct) {
            $types[] = 'Acto administrativo de creación';
        }

        return $types;
    }

    /**
     * @param  array<int, string>  $extra
     * @return array<int, string>
     */
    private function informeTypes(array $extra = [], bool $includeOficioRemisorio = true): array
    {
        $types = $includeOficioRemisorio ? ['Oficio remisorio'] : [];
        $types[] = 'Informe';

        return [...$types, ...$extra];
    }
}
