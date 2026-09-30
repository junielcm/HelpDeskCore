export const statusOptions = {
    open: { label: 'Abierto', badge: 'bg-sky-50 text-sky-700 ring-sky-600/20' },
    in_progress: { label: 'En Proceso', badge: 'bg-blue-50 text-blue-700 ring-blue-600/20' },
    resolved: { label: 'Resuelto', badge: 'bg-emerald-50 text-emerald-700 ring-emerald-600/20' },
    closed: { label: 'Cerrado', badge: 'bg-slate-100 text-slate-600 ring-slate-500/20' },
};

export const priorityOptions = {
    urgent: { label: 'Urgente', badge: 'bg-amber-100 text-amber-800 ring-amber-600/20' },
    high: { label: 'Alta', badge: 'bg-orange-100 text-orange-800 ring-orange-600/20' },
    medium: { label: 'Media', badge: 'bg-blue-100 text-blue-700 ring-blue-600/20' },
    low: { label: 'Baja', badge: 'bg-slate-100 text-slate-600 ring-slate-500/20' },
};

export const staffRoles = ['agent', 'supervisor', 'admin'];

export function isStaffRole(role) {
    return staffRoles.includes(role);
}