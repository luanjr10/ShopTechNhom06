interface StatusToggleProps {
  checked: boolean;
  onChange: (checked: boolean) => void;
  label: string;
  activeText: string;
  inactiveText: string;
  id?: string;
}

export default function StatusToggle({
  checked,
  onChange,
  label,
  activeText,
  inactiveText,
  id = "status-toggle",
}: StatusToggleProps) {
  return (
    <div className="flex items-center justify-between rounded-xl border border-slate-800 bg-[#0b1120]/60 p-4 transition-colors">
      <div>
        <label
          className="text-sm font-semibold text-slate-200 block cursor-pointer"
          htmlFor={id}
        >
          {label}
        </label>
        <p className="text-xs text-slate-400 mt-0.5">
          {checked ? activeText : inactiveText}
        </p>
      </div>

      <button
        id={id}
        type="button"
        role="switch"
        aria-checked={checked}
        onClick={() => onChange(!checked)}
        className={`relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-indigo-500/40 ${
          checked ? "bg-indigo-600" : "bg-slate-700"
        }`}
      >
        <span
          className={`pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow-md ring-0 transition duration-200 ease-in-out ${
            checked ? "translate-x-5" : "translate-x-0"
          }`}
        />
      </button>
    </div>
  );
}
