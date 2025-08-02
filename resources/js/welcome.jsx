import React from 'react';
import { createRoot } from 'react-dom/client';
import {
    FileText,
    Building2,
    Workflow,
    Shield,
    Search,
    Bell,
    BarChart3,
    Zap,
    Users,
    Globe,
    CheckCircle,
    ArrowRight,
    Star,
    Database,
    Cloud,
    Smartphone,
    Lock,
    TrendingUp,
    Award,
    Target,
    Briefcase,
    GraduationCap,
    Factory,
    Heart,
    Building
} from 'lucide-react';

function WelcomePage() {
    // Obtener datos de Laravel
    const appData = window.appData || {};
    const { isAuthenticated, user, adminUrl, loginUrl, stats } = appData;

    return (
        <div className="min-h-screen bg-white">
            {/* Navigation */}
            <nav className="bg-gray-900 shadow-sm border-b border-gray-800 sticky top-0 z-50">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div className="flex justify-between items-center h-16">
                        <div className="flex items-center space-x-3">
                            <img src="/images/logo_archive_master.png" alt="ArchiveMaster Logo" className="w-10 h-10 rounded-lg"/>
                            <span className="text-2xl font-bold bg-gradient-to-r from-blue-600 to-blue-800 bg-clip-text text-transparent">ArchiveMaster</span>
                        </div>
                        <div className="hidden md:flex items-center space-x-8">
                            <a href="#features" className="text-gray-300 hover:text-green-400 transition-colors">Características</a>
                            <a href="#technology" className="text-gray-300 hover:text-green-400 transition-colors">Tecnología</a>
                            <a href="#industries" className="text-gray-300 hover:text-green-400 transition-colors">Industrias</a>
                            <button
                                onClick={() => window.location.href = isAuthenticated ? adminUrl : loginUrl}
                                className="bg-gradient-to-r from-green-600 to-green-700 text-white px-6 py-2 rounded-lg hover:from-green-700 hover:to-green-800 transition-all duration-200 shadow-lg hover:shadow-xl"
                            >
                                {isAuthenticated ? 'Ir al Dashboard' : 'Acceder al Sistema'}
                            </button>
                        </div>
                    </div>
                </div>
            </nav>

            {/* Hero Section */}
            <section className="relative bg-gradient-to-br from-gray-900 via-gray-800 to-green-900 pt-20 pb-32 overflow-hidden text-white">
                <div className="absolute inset-0 bg-grid-pattern opacity-5"></div>
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative">
                    {/* Badge */}
                    <div className="text-center mb-12">
                        <div className="inline-flex items-center bg-green-100 text-green-800 px-4 py-2 rounded-full text-sm font-medium">
                            <CheckCircle className="w-4 h-4 mr-2" />
                            100% Completo • Listo para Producción
                        </div>
                    </div>

                    {/* Hero Grid */}
                    <div className="grid lg:grid-cols-2 gap-12 items-center">
                        {/* Logo Side */}
                        <div className="flex justify-center lg:justify-end order-2 lg:order-1">
                            <div className="relative">
                                <div className="absolute inset-0 bg-gradient-to-r from-green-400 to-emerald-400 rounded-full blur-3xl opacity-20 animate-pulse"></div>
                                <img
                                    src="/images/logo_archive_master.png"
                                    alt="ArchiveMaster Logo"
                                    className="relative w-64 h-64 md:w-80 md:h-80 lg:w-96 lg:h-96 rounded-3xl shadow-2xl transform hover:scale-105 transition-transform duration-300"
                                />
                                <div className="absolute -bottom-4 -right-4 bg-green-500 text-white px-4 py-2 rounded-full text-sm font-semibold shadow-lg">
                                    v2.0
                                </div>
                            </div>
                        </div>

                        {/* Content Side */}
                        <div className="text-center lg:text-left order-1 lg:order-2">
                            <h1 className="text-4xl md:text-6xl lg:text-7xl font-bold text-white mb-6 leading-tight">
                                La Gestión Documental
                                <span className="block bg-gradient-to-r from-green-400 to-emerald-400 bg-clip-text text-transparent">del Futuro</span>
                            </h1>
                            <p className="text-lg md:text-xl text-gray-300 mb-8 leading-relaxed">
                                Sistema empresarial completo desarrollado en <strong>Laravel 12 + Filament 3</strong> que revoluciona la gestión documental con workflows inteligentes, búsqueda avanzada y seguridad empresarial.
                            </p>
                            <div className="flex flex-col sm:flex-row gap-4 justify-center lg:justify-start mb-12">
                                <button
                                    onClick={() => window.location.href = isAuthenticated ? adminUrl : loginUrl}
                                    className="bg-gradient-to-r from-green-600 to-green-700 text-white px-8 py-4 rounded-xl text-lg font-semibold hover:from-green-700 hover:to-green-800 transition-all duration-200 shadow-xl hover:shadow-2xl transform hover:-translate-y-1 flex items-center justify-center"
                                >
                                    {isAuthenticated ? 'Ir al Dashboard' : 'Acceder al Sistema'}
                                    <ArrowRight className="w-5 h-5 ml-2" />
                                </button>
                                <button className="border-2 border-gray-400 text-gray-300 px-8 py-4 rounded-xl text-lg font-semibold hover:border-green-400 hover:text-green-400 transition-all duration-200 flex items-center justify-center">
                                    Ver Documentación
                                    <FileText className="w-5 h-5 ml-2" />
                                </button>
                            </div>
                        </div>
                    </div>

                    {/* Metrics */}
                    <div className="mt-20">
                        <div className="grid grid-cols-2 md:grid-cols-4 gap-8 max-w-4xl mx-auto">
                            <div className="text-center">
                                <div className="text-3xl font-bold text-green-400 mb-2">{stats?.resources || 14}</div>
                                <div className="text-gray-300">Resources Completos</div>
                            </div>
                            <div className="text-center">
                                <div className="text-3xl font-bold text-green-400 mb-2">{stats?.widgets || 25}+</div>
                                <div className="text-gray-300">Widgets Dashboard</div>
                            </div>
                            <div className="text-center">
                                <div className="text-3xl font-bold text-green-400 mb-2">{stats?.endpoints || 50}+</div>
                                <div className="text-gray-300">Endpoints API</div>
                            </div>
                            <div className="text-center">
                                <div className="text-3xl font-bold text-green-400 mb-2">{stats?.users || 1000}+</div>
                                <div className="text-gray-300">Usuarios Concurrentes</div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            {/* Features Section */}
            <section id="features" className="py-24 bg-white">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div className="text-center mb-20">
                        <h2 className="text-4xl md:text-5xl font-bold text-gray-900 mb-6">Características que Transforman</h2>
                        <p className="text-xl text-gray-600 max-w-3xl mx-auto">Una plataforma completa con todas las funcionalidades que necesita una empresa moderna</p>
                    </div>
                    <div className="grid md:grid-cols-2 lg:grid-cols-3 gap-8">
                        <div className="bg-gradient-to-br from-green-50 to-emerald-50 p-8 rounded-2xl border border-green-100 hover:shadow-xl transition-all duration-300 transform hover:-translate-y-2">
                            <div className="w-12 h-12 bg-green-600 rounded-xl flex items-center justify-center mb-6">
                                <Building2 className="w-6 h-6 text-white" />
                            </div>
                            <h3 className="text-xl font-bold text-gray-900 mb-4">Multi-Empresa</h3>
                            <p className="text-gray-600 mb-4">Gestión de múltiples empresas con estructura jerárquica: Empresas → Sedes → Departamentos → Usuarios</p>
                            <ul className="text-sm text-gray-500 space-y-2">
                                <li className="flex items-center"><CheckCircle className="w-4 h-4 text-green-500 mr-2" />Aislamiento completo de datos</li>
                                <li className="flex items-center"><CheckCircle className="w-4 h-4 text-green-500 mr-2" />Escalabilidad garantizada</li>
                            </ul>
                        </div>
                        <div className="bg-gradient-to-br from-teal-50 to-cyan-50 p-8 rounded-2xl border border-teal-100 hover:shadow-xl transition-all duration-300 transform hover:-translate-y-2">
                            <div className="w-12 h-12 bg-teal-600 rounded-xl flex items-center justify-center mb-6">
                                <Workflow className="w-6 h-6 text-white" />
                            </div>
                            <h3 className="text-xl font-bold text-gray-900 mb-4">Workflows Inteligentes</h3>
                            <p className="text-gray-600 mb-4">Motor de workflows con estados configurables, SLA automático y escalamiento inteligente</p>
                            <ul className="text-sm text-gray-500 space-y-2">
                                <li className="flex items-center"><CheckCircle className="w-4 h-4 text-green-500 mr-2" />Estados personalizables</li>
                                <li className="flex items-center"><CheckCircle className="w-4 h-4 text-green-500 mr-2" />Alertas automáticas</li>
                            </ul>
                        </div>
                        <div className="bg-gradient-to-br from-emerald-50 to-green-50 p-8 rounded-2xl border border-emerald-100 hover:shadow-xl transition-all duration-300 transform hover:-translate-y-2">
                            <div className="w-12 h-12 bg-emerald-600 rounded-xl flex items-center justify-center mb-6">
                                <Search className="w-6 h-6 text-white" />
                            </div>
                            <h3 className="text-xl font-bold text-gray-900 mb-4">Búsqueda Avanzada</h3>
                            <p className="text-gray-600 mb-4">Motor de búsqueda con Laravel Scout + Meilisearch para búsquedas ultrarrápidas y precisas</p>
                            <ul className="text-sm text-gray-500 space-y-2">
                                <li className="flex items-center"><CheckCircle className="w-4 h-4 text-green-500 mr-2" />Búsqueda full-text</li>
                                <li className="flex items-center"><CheckCircle className="w-4 h-4 text-green-500 mr-2" />OCR integrado</li>
                            </ul>
                        </div>
                        <div className="bg-gradient-to-br from-orange-50 to-red-50 p-8 rounded-2xl border border-orange-100 hover:shadow-xl transition-all duration-300 transform hover:-translate-y-2">
                            <div className="w-12 h-12 bg-orange-600 rounded-xl flex items-center justify-center mb-6">
                                <Bell className="w-6 h-6 text-white" />
                            </div>
                            <h3 className="text-xl font-bold text-gray-900 mb-4">Notificaciones Automáticas</h3>
                            <p className="text-gray-600 mb-4">Sistema completo de notificaciones in-app, email y alertas SLA con escalamiento automático</p>
                            <ul className="text-sm text-gray-500 space-y-2">
                                <li className="flex items-center"><CheckCircle className="w-4 h-4 text-green-500 mr-2" />Tiempo real</li>
                                <li className="flex items-center"><CheckCircle className="w-4 h-4 text-green-500 mr-2" />Personalizables</li>
                            </ul>
                        </div>
                        <div className="bg-gradient-to-br from-lime-50 to-green-50 p-8 rounded-2xl border border-lime-100 hover:shadow-xl transition-all duration-300 transform hover:-translate-y-2">
                            <div className="w-12 h-12 bg-lime-600 rounded-xl flex items-center justify-center mb-6">
                                <BarChart3 className="w-6 h-6 text-white" />
                            </div>
                            <h3 className="text-xl font-bold text-gray-900 mb-4">Reportes Empresariales</h3>
                            <p className="text-gray-600 mb-4">Motor de reportes con constructor dinámico, programación automática y múltiples formatos</p>
                            <ul className="text-sm text-gray-500 space-y-2">
                                <li className="flex items-center"><CheckCircle className="w-4 h-4 text-green-500 mr-2" />KPIs por departamento</li>
                                <li className="flex items-center"><CheckCircle className="w-4 h-4 text-green-500 mr-2" />Dashboards interactivos</li>
                            </ul>
                        </div>
                        <div className="bg-gradient-to-br from-gray-50 to-slate-50 p-8 rounded-2xl border border-gray-100 hover:shadow-xl transition-all duration-300 transform hover:-translate-y-2">
                            <div className="w-12 h-12 bg-gray-600 rounded-xl flex items-center justify-center mb-6">
                                <Shield className="w-6 h-6 text-white" />
                            </div>
                            <h3 className="text-xl font-bold text-gray-900 mb-4">Seguridad Empresarial</h3>
                            <p className="text-gray-600 mb-4">Autenticación robusta, autorización granular, auditoría completa y protección avanzada</p>
                            <ul className="text-sm text-gray-500 space-y-2">
                                <li className="flex items-center"><CheckCircle className="w-4 h-4 text-green-500 mr-2" />Roles jerárquicos</li>
                                <li className="flex items-center"><CheckCircle className="w-4 h-4 text-green-500 mr-2" />Encriptación AES-256</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </section>

            {/* Technology Stack */}
            <section id="technology" className="py-24 bg-gradient-to-br from-gray-50 to-green-50">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div className="text-center mb-20">
                        <h2 className="text-4xl md:text-5xl font-bold text-gray-900 mb-6">Tecnología de Vanguardia</h2>
                        <p className="text-xl text-gray-600 max-w-3xl mx-auto">Construido con las mejores tecnologías para garantizar performance, escalabilidad y mantenibilidad</p>
                    </div>
                    <div className="grid md:grid-cols-2 lg:grid-cols-4 gap-8 mb-16">
                        <div className="bg-white p-6 rounded-xl shadow-lg text-center hover:shadow-xl transition-shadow">
                            <div className="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
                                <span className="text-2xl font-bold text-red-600">L</span>
                            </div>
                            <h3 className="font-bold text-gray-900 mb-2">Laravel 12</h3>
                            <p className="text-gray-600 text-sm">Framework PHP moderno y robusto</p>
                        </div>
                        <div className="bg-white p-6 rounded-xl shadow-lg text-center hover:shadow-xl transition-shadow">
                            <div className="w-16 h-16 bg-yellow-100 rounded-full flex items-center justify-center mx-auto mb-4">
                                <span className="text-2xl font-bold text-yellow-600">F</span>
                            </div>
                            <h3 className="font-bold text-gray-900 mb-2">Filament 3</h3>
                            <p className="text-gray-600 text-sm">Panel administrativo moderno</p>
                        </div>
                        <div className="bg-white p-6 rounded-xl shadow-lg text-center hover:shadow-xl transition-shadow">
                            <div className="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
                                <Database className="w-8 h-8 text-green-600" />
                            </div>
                            <h3 className="font-bold text-gray-900 mb-2">MySQL + Redis</h3>
                            <p className="text-gray-600 text-sm">Base de datos y cache optimizado</p>
                        </div>
                        <div className="bg-white p-6 rounded-xl shadow-lg text-center hover:shadow-xl transition-shadow">
                            <div className="w-16 h-16 bg-purple-100 rounded-full flex items-center justify-center mx-auto mb-4">
                                <Search className="w-8 h-8 text-purple-600" />
                            </div>
                            <h3 className="font-bold text-gray-900 mb-2">Meilisearch</h3>
                            <p className="text-gray-600 text-sm">Motor de búsqueda ultrarrápido</p>
                        </div>
                    </div>
                    <div className="bg-white rounded-2xl p-8 shadow-xl">
                        <h3 className="text-2xl font-bold text-gray-900 mb-8 text-center">Arquitectura Empresarial</h3>
                        <div className="grid md:grid-cols-3 gap-8">
                            <div className="text-center">
                                <div className="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
                                    <Cloud className="w-8 h-8 text-green-600" />
                                </div>
                                <h4 className="font-bold text-gray-900 mb-2">API First</h4>
                                <p className="text-gray-600 text-sm">50+ endpoints REST con documentación Swagger completa</p>
                            </div>
                            <div className="text-center">
                                <div className="w-16 h-16 bg-emerald-100 rounded-full flex items-center justify-center mx-auto mb-4">
                                    <Zap className="w-8 h-8 text-emerald-600" />
                                </div>
                                <h4 className="font-bold text-gray-900 mb-2">Performance</h4>
                                <p className="text-gray-600 text-sm">Cache Redis, CDN y optimizaciones para máximo rendimiento</p>
                            </div>
                            <div className="text-center">
                                <div className="w-16 h-16 bg-teal-100 rounded-full flex items-center justify-center mx-auto mb-4">
                                    <Lock className="w-8 h-8 text-teal-600" />
                                </div>
                                <h4 className="font-bold text-gray-900 mb-2">Seguridad</h4>
                                <p className="text-gray-600 text-sm">Autenticación Sanctum, roles granulares y auditoría completa</p>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            {/* Industries */}
            <section id="industries" className="py-24 bg-white">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div className="text-center mb-20">
                        <h2 className="text-4xl md:text-5xl font-bold text-gray-900 mb-6">Para Todas las Industrias</h2>
                        <p className="text-xl text-gray-600 max-w-3xl mx-auto">Solución versátil que se adapta a las necesidades específicas de cada sector</p>
                    </div>
                    <div className="grid md:grid-cols-2 lg:grid-cols-3 gap-8">
                        <div className="bg-gradient-to-br from-green-50 to-emerald-50 p-8 rounded-2xl border border-green-100 hover:shadow-xl transition-all duration-300">
                            <Building className="w-12 h-12 text-green-600 mb-6" />
                            <h3 className="text-xl font-bold text-gray-900 mb-4">Sector Gubernamental</h3>
                            <ul className="text-gray-600 space-y-2">
                                <li>• Control de expedientes ciudadanos</li>
                                <li>• Gestión de contratos públicos</li>
                                <li>• Archivo histórico digitalizado</li>
                                <li>• Cumplimiento de normativas</li>
                            </ul>
                        </div>
                        <div className="bg-gradient-to-br from-red-50 to-pink-50 p-8 rounded-2xl border border-red-100 hover:shadow-xl transition-all duration-300">
                            <Heart className="w-12 h-12 text-red-600 mb-6" />
                            <h3 className="text-xl font-bold text-gray-900 mb-4">Sector Salud</h3>
                            <ul className="text-gray-600 space-y-2">
                                <li>• Historias clínicas digitales</li>
                                <li>• Gestión de documentos médicos</li>
                                <li>• Control de certificados</li>
                                <li>• Cumplimiento de auditorías</li>
                            </ul>
                        </div>
                        <div className="bg-gradient-to-br from-gray-50 to-slate-50 p-8 rounded-2xl border border-gray-100 hover:shadow-xl transition-all duration-300">
                            <Factory className="w-12 h-12 text-gray-600 mb-6" />
                            <h3 className="text-xl font-bold text-gray-900 mb-4">Sector Industrial</h3>
                            <ul className="text-gray-600 space-y-2">
                                <li>• Gestión de certificaciones</li>
                                <li>• Control de procedimientos</li>
                                <li>• Documentos de calidad</li>
                                <li>• Normativas de seguridad</li>
                            </ul>
                        </div>
                        <div className="bg-gradient-to-br from-teal-50 to-cyan-50 p-8 rounded-2xl border border-teal-100 hover:shadow-xl transition-all duration-300">
                            <Briefcase className="w-12 h-12 text-teal-600 mb-6" />
                            <h3 className="text-xl font-bold text-gray-900 mb-4">Sector Corporativo</h3>
                            <ul className="text-gray-600 space-y-2">
                                <li>• Gestión de contratos</li>
                                <li>• Documentos legales</li>
                                <li>• Políticas internas</li>
                                <li>• Recursos humanos</li>
                            </ul>
                        </div>
                        <div className="bg-gradient-to-br from-purple-50 to-indigo-50 p-8 rounded-2xl border border-purple-100 hover:shadow-xl transition-all duration-300">
                            <GraduationCap className="w-12 h-12 text-purple-600 mb-6" />
                            <h3 className="text-xl font-bold text-gray-900 mb-4">Sector Educativo</h3>
                            <ul className="text-gray-600 space-y-2">
                                <li>• Expedientes académicos</li>
                                <li>• Documentos administrativos</li>
                                <li>• Certificaciones</li>
                                <li>• Archivo histórico</li>
                            </ul>
                        </div>
                        <div className="bg-gradient-to-br from-yellow-50 to-orange-50 p-8 rounded-2xl border border-yellow-100 hover:shadow-xl transition-all duration-300">
                            <Globe className="w-12 h-12 text-yellow-600 mb-6" />
                            <h3 className="text-xl font-bold text-gray-900 mb-4">Multi-Sector</h3>
                            <ul className="text-gray-600 space-y-2">
                                <li>• Configuración flexible</li>
                                <li>• Workflows personalizables</li>
                                <li>• Integración con sistemas existentes</li>
                                <li>• Escalabilidad garantizada</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </section>

            {/* Value Proposition */}
            <section className="py-24 bg-gradient-to-br from-green-600 to-emerald-700 text-white">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div className="text-center mb-16">
                        <h2 className="text-4xl md:text-5xl font-bold mb-6">Propuesta de Valor</h2>
                        <p className="text-xl opacity-90 max-w-3xl mx-auto">Diferentes beneficios para cada rol en su organización</p>
                    </div>
                    <div className="grid md:grid-cols-3 gap-8">
                        <div className="bg-white/10 backdrop-blur-sm p-8 rounded-2xl border border-white/20">
                            <Target className="w-12 h-12 text-yellow-400 mb-6" />
                            <h3 className="text-2xl font-bold mb-4">Para Decision Makers</h3>
                            <p className="opacity-90 mb-6">"Una solución completa que transforma la gestión documental de su organización, aumentando la productividad hasta un 300% y garantizando el cumplimiento total."</p>
                            <ul className="space-y-2 opacity-80">
                                <li className="flex items-center"><CheckCircle className="w-4 h-4 mr-2" />ROI Inmediato</li>
                                <li className="flex items-center"><CheckCircle className="w-4 h-4 mr-2" />Cumplimiento Garantizado</li>
                                <li className="flex items-center"><CheckCircle className="w-4 h-4 mr-2" />Reducción de Costos</li>
                            </ul>
                        </div>
                        <div className="bg-white/10 backdrop-blur-sm p-8 rounded-2xl border border-white/20">
                            <Award className="w-12 h-12 text-blue-400 mb-6" />
                            <h3 className="text-2xl font-bold mb-4">Para IT Managers</h3>
                            <p className="opacity-90 mb-6">"Arquitectura moderna y escalable basada en Laravel 12, con API completa, seguridad empresarial y capacidades de integración."</p>
                            <ul className="space-y-2 opacity-80">
                                <li className="flex items-center"><CheckCircle className="w-4 h-4 mr-2" />Tecnología Moderna</li>
                                <li className="flex items-center"><CheckCircle className="w-4 h-4 mr-2" />API Completa</li>
                                <li className="flex items-center"><CheckCircle className="w-4 h-4 mr-2" />Fácil Integración</li>
                            </ul>
                        </div>
                        <div className="bg-white/10 backdrop-blur-sm p-8 rounded-2xl border border-white/20">
                            <Users className="w-12 h-12 text-cyan-400 mb-6" />
                            <h3 className="text-2xl font-bold mb-4">Para End Users</h3>
                            <p className="opacity-90 mb-6">"Interface intuitiva que permite encontrar cualquier documento en segundos, con notificaciones automáticas y flujos que eliminan la burocracia."</p>
                            <ul className="space-y-2 opacity-80">
                                <li className="flex items-center"><CheckCircle className="w-4 h-4 mr-2" />Interface Intuitiva</li>
                                <li className="flex items-center"><CheckCircle className="w-4 h-4 mr-2" />Búsqueda Rápida</li>
                                <li className="flex items-center"><CheckCircle className="w-4 h-4 mr-2" />Acceso Móvil</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </section>

            {/* Competitive Advantages */}
            <section className="py-24 bg-white">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div className="text-center mb-20">
                        <h2 className="text-4xl md:text-5xl font-bold text-gray-900 mb-6">¿Por Qué Elegir ArchiveMaster?</h2>
                        <p className="text-xl text-gray-600 max-w-3xl mx-auto">Ventajas competitivas que nos posicionan como la mejor opción del mercado</p>
                    </div>
                    <div className="grid md:grid-cols-2 lg:grid-cols-3 gap-8">
                        <div className="text-center p-6">
                            <div className="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-6">
                                <CheckCircle className="w-8 h-8 text-green-600" />
                            </div>
                            <h3 className="text-xl font-bold text-gray-900 mb-4">100% Completo</h3>
                            <p className="text-gray-600">Listo para producción con todas las funcionalidades implementadas y probadas</p>
                        </div>
                        <div className="text-center p-6">
                            <div className="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-6">
                                <Zap className="w-8 h-8 text-green-600" />
                            </div>
                            <h3 className="text-xl font-bold text-gray-900 mb-4">Tecnología de Vanguardia</h3>
                            <p className="text-gray-600">Arquitectura moderna con Laravel 12 y las mejores prácticas de desarrollo</p>
                        </div>
                        <div className="text-center p-6">
                            <div className="w-16 h-16 bg-yellow-100 rounded-full flex items-center justify-center mx-auto mb-6">
                                <TrendingUp className="w-8 h-8 text-yellow-600" />
                            </div>
                            <h3 className="text-xl font-bold text-gray-900 mb-4">ROI Inmediato</h3>
                            <p className="text-gray-600">Resultados medibles desde el primer día con aumento de productividad del 300%</p>
                        </div>
                        <div className="text-center p-6">
                            <div className="w-16 h-16 bg-teal-100 rounded-full flex items-center justify-center mx-auto mb-6">
                                <Globe className="w-8 h-8 text-teal-600" />
                            </div>
                            <h3 className="text-xl font-bold text-gray-900 mb-4">Escalabilidad Garantizada</h3>
                            <p className="text-gray-600">Crece con su organización sin limitaciones de usuarios o documentos</p>
                        </div>
                        <div className="text-center p-6">
                            <div className="w-16 h-16 bg-emerald-100 rounded-full flex items-center justify-center mx-auto mb-6">
                                <FileText className="w-8 h-8 text-emerald-600" />
                            </div>
                            <h3 className="text-xl font-bold text-gray-900 mb-4">Soporte Completo</h3>
                            <p className="text-gray-600">Documentación exhaustiva, API Swagger y soporte técnico especializado</p>
                        </div>
                        <div className="text-center p-6">
                            <div className="w-16 h-16 bg-lime-100 rounded-full flex items-center justify-center mx-auto mb-6">
                                <Star className="w-8 h-8 text-lime-600" />
                            </div>
                            <h3 className="text-xl font-bold text-gray-900 mb-4">Futuro-Proof</h3>
                            <p className="text-gray-600">Capacidades de extensión ilimitadas y roadmap de evolución continua</p>
                        </div>
                    </div>
                </div>
            </section>

            {/* CTA Section */}
            <section className="py-24 bg-gradient-to-r from-green-600 to-emerald-600 text-white">
                <div className="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
                    <h2 className="text-4xl md:text-5xl font-bold mb-6">La Gestión Documental del Futuro, Disponible Hoy</h2>
                    <p className="text-xl opacity-90 mb-12 max-w-3xl mx-auto">Transforme su organización con ArchiveMaster. Solicite una demostración personalizada y descubra cómo podemos revolucionar su gestión documental.</p>
                    <div className="flex flex-col sm:flex-row gap-4 justify-center">
                        <button
                            onClick={() => window.location.href = isAuthenticated ? adminUrl : loginUrl}
                            className="bg-white text-green-600 px-8 py-4 rounded-xl text-lg font-semibold hover:bg-gray-100 transition-all duration-200 shadow-xl hover:shadow-2xl transform hover:-translate-y-1 flex items-center justify-center"
                        >
                            {isAuthenticated ? 'Ir al Dashboard' : 'Acceder al Sistema'}
                            <ArrowRight className="w-5 h-5 ml-2" />
                        </button>
                        <button className="border-2 border-white text-white px-8 py-4 rounded-xl text-lg font-semibold hover:bg-white hover:text-green-600 transition-all duration-200 flex items-center justify-center">
                            Descargar Documentación
                            <FileText className="w-5 h-5 ml-2" />
                        </button>
                    </div>
                </div>
            </section>

            {/* Footer */}
            <footer className="bg-gray-900 text-white py-16">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div className="grid md:grid-cols-4 gap-8">
                        <div className="col-span-2">
                            <div className="flex items-center space-x-3 mb-6">
                                <img src="/images/logo_archive_master.png" alt="ArchiveMaster Logo" className="w-10 h-10 rounded-lg"/>
                                <span className="text-2xl font-bold">ArchiveMaster</span>
                            </div>
                            <p className="text-gray-400 mb-6 max-w-md">Sistema empresarial de gestión documental desarrollado con Laravel 12 + Filament 3. La solución completa para la transformación digital de su organización.</p>
                            <div className="flex space-x-4">
                                <div className="w-10 h-10 bg-gray-800 rounded-lg flex items-center justify-center hover:bg-gray-700 transition-colors cursor-pointer">
                                    <span className="text-sm font-bold">API</span>
                                </div>
                                <div className="w-10 h-10 bg-gray-800 rounded-lg flex items-center justify-center hover:bg-gray-700 transition-colors cursor-pointer">
                                    <FileText className="w-5 h-5" />
                                </div>
                            </div>
                        </div>
                        <div>
                            <h3 className="font-bold mb-4">Producto</h3>
                            <ul className="space-y-2 text-gray-400">
                                <li><a href="#features" className="hover:text-white transition-colors">Características</a></li>
                                <li><a href={isAuthenticated ? adminUrl : loginUrl} className="hover:text-white transition-colors">Sistema</a></li>
                                <li><a href="#" className="hover:text-white transition-colors">Documentación</a></li>
                                <li><a href="#" className="hover:text-white transition-colors">API</a></li>
                            </ul>
                        </div>
                        <div>
                            <h3 className="font-bold mb-4">Soporte</h3>
                            <ul className="space-y-2 text-gray-400">
                                <li><a href="#" className="hover:text-white transition-colors">Centro de Ayuda</a></li>
                                <li><a href="#" className="hover:text-white transition-colors">Contacto</a></li>
                                <li><a href={isAuthenticated ? adminUrl : loginUrl} className="hover:text-white transition-colors">Demo</a></li>
                                <li><a href="#" className="hover:text-white transition-colors">Consultoría</a></li>
                            </ul>
                        </div>
                    </div>
                    <div className="border-t border-gray-800 mt-12 pt-8">
                        <div className="flex flex-col md:flex-row justify-between items-center mb-4">
                            <p className="text-gray-400">© 2025 ArchiveMaster. Todos los derechos reservados.</p>
                            <p className="text-gray-400 mt-4 md:mt-0">Desarrollado con Laravel 12 + Filament 3</p>
                        </div>
                        <div className="text-center border-t border-gray-800 pt-4">
                            <p className="text-gray-500 text-sm mb-2">Desarrollado por</p>
                            <div className="flex justify-center items-center space-x-6">
                                <a
                                    href="https://github.com/korozcolt"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    className="text-gray-400 hover:text-white transition-colors flex items-center space-x-2"
                                >
                                    <svg className="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                                        <path d="M12 0c-6.626 0-12 5.373-12 12 0 5.302 3.438 9.8 8.207 11.387.599.111.793-.261.793-.577v-2.234c-3.338.726-4.033-1.416-4.033-1.416-.546-1.387-1.333-1.756-1.333-1.756-1.089-.745.083-.729.083-.729 1.205.084 1.839 1.237 1.839 1.237 1.07 1.834 2.807 1.304 3.492.997.107-.775.418-1.305.762-1.604-2.665-.305-5.467-1.334-5.467-5.931 0-1.311.469-2.381 1.236-3.221-.124-.303-.535-1.524.117-3.176 0 0 1.008-.322 3.301 1.23.957-.266 1.983-.399 3.003-.404 1.02.005 2.047.138 3.006.404 2.291-1.552 3.297-1.23 3.297-1.23.653 1.653.242 2.874.118 3.176.77.84 1.235 1.911 1.235 3.221 0 4.609-2.807 5.624-5.479 5.921.43.372.823 1.102.823 2.222v3.293c0 .319.192.694.801.576 4.765-1.589 8.199-6.086 8.199-11.386 0-6.627-5.373-12-12-12z"/>
                                    </svg>
                                    <span className="font-semibold">Kronnos</span>
                                </a>
                                <a
                                    href="https://korozcolt.github.io/"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    className="text-gray-400 hover:text-white transition-colors flex items-center space-x-2"
                                >
                                    <Globe className="w-5 h-5" />
                                    <span>Portfolio</span>
                                </a>
                                <a
                                    href="https://www.instagram.com/krisnf_oficial"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    className="text-gray-400 hover:text-pink-400 transition-colors flex items-center space-x-2"
                                >
                                    <svg className="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                                        <path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/>
                                    </svg>
                                    <span>Instagram</span>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </footer>
        </div>
    );
}

// Montar el componente en el DOM
const container = document.getElementById('welcome-app');
if (container) {
    const root = createRoot(container);
    root.render(<WelcomePage />);
}

export default WelcomePage;
