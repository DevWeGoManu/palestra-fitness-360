export function Toast({ toast, onClose }) {
  if (!toast) return null;
  return (
    <button className={`toast ${toast.type || 'info'}`} role="status" aria-live="polite" onClick={onClose}>
      {toast.message}
    </button>
  );
}
