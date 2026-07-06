'use client';

export default function Toast({ message, type }: { message: string; type: 'success' | 'error' }) {
  return (
    <div className="toast-container">
      <div className={`toast ${type}`}>{message}</div>
    </div>
  );
}
