import {
    ApartmentOutlined,
    AppstoreOutlined,
    BankOutlined,
    BulbOutlined,
    CloudOutlined,
    CrownOutlined,
    DatabaseOutlined,
    ExperimentOutlined,
    FireOutlined,
    GlobalOutlined,
    HeartOutlined,
    RocketOutlined,
    SafetyCertificateOutlined,
    ShopOutlined,
    SmileOutlined,
    StarOutlined,
    TeamOutlined,
    ThunderboltOutlined,
    ToolOutlined,
    TrophyOutlined,
} from '@ant-design/icons';
import type { PlanIconName } from '@/types';

export type { PlanIconName } from '@/types';

const planIcons = {
    ApartmentOutlined,
    AppstoreOutlined,
    BankOutlined,
    BulbOutlined,
    CloudOutlined,
    CrownOutlined,
    DatabaseOutlined,
    ExperimentOutlined,
    FireOutlined,
    GlobalOutlined,
    HeartOutlined,
    RocketOutlined,
    SafetyCertificateOutlined,
    ShopOutlined,
    SmileOutlined,
    StarOutlined,
    TeamOutlined,
    ThunderboltOutlined,
    ToolOutlined,
    TrophyOutlined,
};

const iconLabels: Record<PlanIconName, string> = {
    ApartmentOutlined: 'Organización',
    AppstoreOutlined: 'Aplicaciones',
    BankOutlined: 'Empresa',
    BulbOutlined: 'Innovación',
    CloudOutlined: 'Nube',
    CrownOutlined: 'Corona',
    DatabaseOutlined: 'Datos',
    ExperimentOutlined: 'Experimento',
    FireOutlined: 'Fuego',
    GlobalOutlined: 'Global',
    HeartOutlined: 'Corazón',
    RocketOutlined: 'Cohete',
    SafetyCertificateOutlined: 'Certificado',
    ShopOutlined: 'Tienda',
    SmileOutlined: 'Sonrisa',
    StarOutlined: 'Estrella',
    TeamOutlined: 'Equipo',
    ThunderboltOutlined: 'Rayo',
    ToolOutlined: 'Herramienta',
    TrophyOutlined: 'Trofeo',
};

export function PlanIcon({
    name,
    className,
}: {
    name: PlanIconName;
    className?: string;
}) {
    const Icon = planIcons[name];

    return <Icon className={className} aria-hidden />;
}

export function planIconLabel(name: PlanIconName): string {
    return iconLabels[name];
}
