import { Check, Circle } from 'lucide-react';
import type { ComponentProps, ChangeEventHandler } from 'react';
import { useEffect, useId, useImperativeHandle, useRef, useState } from 'react';
import PasswordInput from '@/components/password-input';
import { cn } from '@/lib/utils';

const passwordRequirements = [
    {
        label: 'Mínimo 12 caracteres',
        test: (password: string) => password.length >= 12,
    },
    {
        label: 'Una letra mayúscula',
        test: (password: string) => /\p{Lu}/u.test(password),
    },
    {
        label: 'Una letra minúscula',
        test: (password: string) => /\p{Ll}/u.test(password),
    },
    {
        label: 'Un número',
        test: (password: string) => /\p{N}/u.test(password),
    },
    {
        label: 'Un carácter especial',
        test: (password: string) => /[\p{P}\p{S}]/u.test(password),
    },
];

const strengthStyles = {
    empty: {
        bar: 'bg-muted',
        text: 'text-muted-foreground',
        label: 'Sin evaluar',
    },
    weak: {
        bar: 'bg-destructive',
        text: 'text-destructive',
        label: 'Débil',
    },
    medium: {
        bar: 'bg-amber-500',
        text: 'text-amber-600 dark:text-amber-400',
        label: 'Media',
    },
    strong: {
        bar: 'bg-emerald-500',
        text: 'text-emerald-600 dark:text-emerald-400',
        label: 'Fuerte',
    },
};

export default function PasswordStrengthInput({
    ref,
    onChange,
    defaultValue,
    ...props
}: ComponentProps<typeof PasswordInput>) {
    const generatedId = useId();
    const inputId = props.id ?? `password-${generatedId}`;
    const requirementsId = `${inputId}-requirements`;
    const inputRef = useRef<HTMLInputElement>(null);
    const [password, setPassword] = useState(
        typeof defaultValue === 'string' ? defaultValue : '',
    );

    useImperativeHandle(ref, () => inputRef.current as HTMLInputElement);

    useEffect(() => {
        const form = inputRef.current?.form;

        if (!form) {
            return;
        }

        const handleReset = () => {
            setPassword(typeof defaultValue === 'string' ? defaultValue : '');
        };

        form.addEventListener('reset', handleReset);

        return () => form.removeEventListener('reset', handleReset);
    }, [defaultValue]);

    const requirements = passwordRequirements.map((requirement) => ({
        ...requirement,
        completed: requirement.test(password),
    }));
    const completedCount = requirements.filter(
        (requirement) => requirement.completed,
    ).length;
    const strength =
        completedCount === passwordRequirements.length
            ? strengthStyles.strong
            : completedCount >= 3
              ? strengthStyles.medium
              : completedCount > 0
                ? strengthStyles.weak
                : strengthStyles.empty;
    const describedBy = [props['aria-describedby'], requirementsId]
        .filter(Boolean)
        .join(' ');
    const handleChange: ChangeEventHandler<HTMLInputElement> = (event) => {
        setPassword(event.target.value);
        onChange?.(event);
    };

    return (
        <div className="grid gap-3">
            <PasswordInput
                {...props}
                id={inputId}
                ref={inputRef}
                defaultValue={defaultValue}
                aria-describedby={describedBy}
                onChange={handleChange}
            />

            <div id={requirementsId} className="grid gap-2">
                <div className="flex items-center justify-between gap-3 text-xs">
                    <span className="text-muted-foreground">
                        Seguridad de contraseña
                    </span>
                    <span className={cn('font-medium', strength.text)}>
                        {strength.label}
                    </span>
                </div>

                <div
                    className="grid grid-cols-5 gap-1"
                    role="progressbar"
                    aria-label="Requisitos de contraseña completados"
                    aria-valuemin={0}
                    aria-valuemax={passwordRequirements.length}
                    aria-valuenow={completedCount}
                    aria-valuetext={`${completedCount} de ${passwordRequirements.length} requisitos completados`}
                >
                    {passwordRequirements.map((requirement, index) => (
                        <span
                            key={requirement.label}
                            className={cn(
                                'h-1.5 rounded-full transition-colors',
                                index < completedCount
                                    ? strength.bar
                                    : 'bg-muted',
                            )}
                            aria-hidden="true"
                        />
                    ))}
                </div>

                <ul className="grid gap-1 text-xs sm:grid-cols-2">
                    {requirements.map((requirement) => (
                        <li
                            key={requirement.label}
                            className={cn(
                                'flex items-center gap-1.5',
                                requirement.completed
                                    ? 'text-emerald-600 dark:text-emerald-400'
                                    : 'text-muted-foreground',
                            )}
                            aria-label={`${requirement.label}: ${requirement.completed ? 'completado' : 'pendiente'}`}
                        >
                            {requirement.completed ? (
                                <Check className="size-3.5 shrink-0" />
                            ) : (
                                <Circle className="size-3.5 shrink-0" />
                            )}
                            <span>{requirement.label}</span>
                        </li>
                    ))}
                </ul>
            </div>
        </div>
    );
}
