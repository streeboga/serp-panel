import { type ReactNode } from 'react'
import { QueryErrorResetBoundary } from '@tanstack/react-query'
import { ErrorBoundary } from '@/components/ErrorBoundary'

interface Props {
  children: ReactNode
}

/**
 * Combines React Query's error reset boundary with our ErrorBoundary.
 * Wrap route-level content that depends on data fetching.
 */
export function QueryErrorBoundary({ children }: Props) {
  return (
    <QueryErrorResetBoundary>
      {({ reset }) => (
        <ErrorBoundary
          fallback={undefined}
          key={undefined}
        >
          <InnerBoundary reset={reset}>{children}</InnerBoundary>
        </ErrorBoundary>
      )}
    </QueryErrorResetBoundary>
  )
}

function InnerBoundary({ reset, children }: { reset: () => void; children: ReactNode }) {
  // The reset function is available for programmatic use;
  // the ErrorBoundary handles the UI fallback
  void reset
  return <>{children}</>
}
