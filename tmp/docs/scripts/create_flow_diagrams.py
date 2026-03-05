import matplotlib.pyplot as plt
from matplotlib.patches import FancyBboxPatch, FancyArrowPatch

OUT = '/Volumes/NAS(MAC)/Data/Herd/archive-master-app/tmp/docs/manual_assets'


def draw_flow(filename, title, nodes, arrows, colors):
    fig, ax = plt.subplots(figsize=(13, 7.2))
    ax.set_xlim(0, 1)
    ax.set_ylim(0, 1)
    ax.axis('off')

    fig.patch.set_facecolor('#0b1220')
    ax.set_facecolor('#0b1220')

    ax.text(0.5, 0.95, title, ha='center', va='center', color='white', fontsize=20, fontweight='bold')

    for idx, (key, x, y, text) in enumerate(nodes):
        patch = FancyBboxPatch(
            (x - 0.12, y - 0.06), 0.24, 0.12,
            boxstyle='round,pad=0.015,rounding_size=0.02',
            linewidth=1.8,
            edgecolor=colors[idx % len(colors)],
            facecolor='#111a2e'
        )
        ax.add_patch(patch)
        ax.text(x, y, text, ha='center', va='center', color='#e5e7eb', fontsize=11, wrap=True)

    node_map = {key: (x, y) for key, x, y, _ in nodes}
    for a, b, label in arrows:
        x1, y1 = node_map[a]
        x2, y2 = node_map[b]
        arr = FancyArrowPatch((x1, y1 - 0.07), (x2, y2 + 0.07), arrowstyle='-|>', mutation_scale=18,
                              linewidth=1.8, color='#60a5fa', connectionstyle='arc3,rad=0.0')
        ax.add_patch(arr)
        mx, my = (x1 + x2) / 2, (y1 + y2) / 2
        if label:
            ax.text(mx, my, label, color='#93c5fd', fontsize=9, ha='center', va='center')

    fig.savefig(f'{OUT}/{filename}', dpi=220, bbox_inches='tight', facecolor=fig.get_facecolor())
    plt.close(fig)


colors = ['#38bdf8', '#818cf8', '#34d399', '#f59e0b', '#f472b6']

# 1) Portal roles overview
draw_flow(
    'flow_portal_roles.png',
    'Flujo Operativo del Portal (Usuarios Operativos)',
    [
        ('rec', 0.2, 0.75, 'Recepcionista\nRegistra y envía'),
        ('mgr', 0.5, 0.75, 'Encargado de Oficina\nRecibe y responde'),
        ('arc', 0.8, 0.75, 'Encargado de Archivo\nUbica y etiqueta'),
        ('usr', 0.2, 0.35, 'Usuario Regular\nConsulta y seguimiento'),
        ('not', 0.5, 0.35, 'Notificaciones\nen tiempo real'),
        ('hist', 0.8, 0.35, 'Trazabilidad\nHistorial completo'),
    ],
    [
        ('rec', 'mgr', 'Distribución'),
        ('mgr', 'arc', 'Cierre / Archivo'),
        ('rec', 'not', 'Eventos'),
        ('mgr', 'not', 'Eventos'),
        ('arc', 'not', 'Eventos'),
        ('usr', 'hist', 'Consulta'),
        ('not', 'hist', 'Registro')
    ],
    colors,
)

# 2) Detailed document lifecycle
draw_flow(
    'flow_document_lifecycle.png',
    'Ciclo de Vida del Documento',
    [
        ('a', 0.15, 0.8, 'Carga de\nDocumento'),
        ('b', 0.35, 0.8, 'Metadatos y\nValidación'),
        ('c', 0.55, 0.8, 'Envío a\nOficina(s)'),
        ('d', 0.75, 0.8, 'Gestión de\nDestino'),
        ('e', 0.35, 0.45, 'Aprobado /\nRechazado'),
        ('f', 0.55, 0.45, 'Recepción en\nArchivo'),
        ('g', 0.75, 0.45, 'Asignación de\nUbicación'),
        ('h', 0.55, 0.18, 'Etiqueta +\nConsulta histórica'),
    ],
    [
        ('a', 'b', ''),
        ('b', 'c', ''),
        ('c', 'd', ''),
        ('d', 'e', 'Decisión'),
        ('e', 'f', 'Si aplica'),
        ('f', 'g', ''),
        ('g', 'h', 'Archivado')
    ],
    colors,
)

# 3) Admin overview
draw_flow(
    'flow_admin_governance.png',
    'Flujo de Administración y Gobierno',
    [
        ('cfg', 0.2, 0.75, 'Configurar\nCatálogos'),
        ('usr', 0.5, 0.75, 'Gestionar\nUsuarios/Roles'),
        ('wf', 0.8, 0.75, 'Definir\nWorkflows'),
        ('ops', 0.2, 0.35, 'Operación\nDiaria'),
        ('rep', 0.5, 0.35, 'Reportes y\nMétricas'),
        ('aud', 0.8, 0.35, 'Auditoría y\nMejora Continua'),
    ],
    [
        ('cfg', 'usr', ''),
        ('usr', 'wf', ''),
        ('wf', 'ops', 'impacta'),
        ('ops', 'rep', ''),
        ('rep', 'aud', ''),
        ('aud', 'cfg', 'retroalimenta')
    ],
    colors,
)

# 4) Notification architecture (functional)
draw_flow(
    'flow_notifications_realtime.png',
    'Flujo Funcional de Notificaciones en Tiempo Real',
    [
        ('u1', 0.2, 0.75, 'Evento de\nNegocio'),
        ('u2', 0.4, 0.75, 'Laravel\nNotification'),
        ('u3', 0.6, 0.75, 'Canales\n(DB/Broadcast)'),
        ('u4', 0.8, 0.75, 'Cliente\nPortal/Admin'),
        ('u5', 0.5, 0.35, 'Centro de\nNotificaciones'),
    ],
    [
        ('u1', 'u2', ''),
        ('u2', 'u3', ''),
        ('u3', 'u4', 'push'),
        ('u4', 'u5', 'render')
    ],
    colors,
)
