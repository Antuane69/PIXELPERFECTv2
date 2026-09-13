import * as React from "react"

import { cn } from "@/lib/utils"

type InputProps = React.ComponentProps<"input"> & {
  prefix?: React.ReactNode
}

function Input({ className, type, prefix, ...props }: InputProps) {
  const input = (
    <input
      type={type}
      data-slot="input"
      className={cn(
        "border-input file:text-foreground placeholder:text-muted-foreground selection:bg-primary selection:text-primary-foreground flex h-9 w-full min-w-0 rounded-md border bg-transparent px-3 py-1 text-base shadow-xs transition-[color,box-shadow] outline-none file:inline-flex file:h-7 file:border-0 file:bg-transparent file:text-sm file:font-medium disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm",
        "focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px]",
        "aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive",
        prefix && "pl-7",
        className
      )}
      {...props}
    />
  )

  if (prefix === undefined) {
    return input
  }

  return (
    <div className="relative w-full">
      <span
        aria-hidden="true"
        className="text-muted-foreground pointer-events-none absolute inset-y-0 left-3 flex items-center text-sm"
      >
        {prefix}
      </span>
      {input}
    </div>
  )
}

export { Input }
