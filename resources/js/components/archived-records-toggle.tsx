import { Link } from '@inertiajs/react';
import { Archive, List } from 'lucide-react';
import { Button } from '@/components/ui/button';

type GetRoute = {
    url: string;
};

type ArchivedRecordsToggleProps = {
    route: GetRoute;
    showingArchived: boolean;
    activeLabel: string;
    archivedLabel: string;
    onToggle?: () => void;
};

export function ArchivedRecordsToggle({
    route,
    showingArchived,
    activeLabel,
    archivedLabel,
    onToggle,
}: ArchivedRecordsToggleProps) {
    if (onToggle) {
        return (
            <Button variant="outline" onClick={onToggle}>
                {showingArchived ? <List /> : <Archive />}
                {showingArchived ? activeLabel : archivedLabel}
            </Button>
        );
    }

    return (
        <Button variant="outline" asChild>
            <Link href={route.url} preserveScroll>
                {showingArchived ? <List /> : <Archive />}
                {showingArchived ? activeLabel : archivedLabel}
            </Link>
        </Button>
    );
}
