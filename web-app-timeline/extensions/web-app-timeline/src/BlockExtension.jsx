// import {
//   reactExtension,
//   useApi,
//   AdminBlock,
//   BlockStack,
//   Text,
// } from '@shopify/ui-extensions-react/admin';

// // The target used here must match the target used in the extension's toml file (./shopify.extension.toml)
// const TARGET = 'admin.product-details.block.render';

// export default reactExtension(TARGET, () => <App />);

// function App() {
//   // The useApi hook provides access to several useful APIs like i18n and data.
//   const {i18n, data} = useApi(TARGET);
//   console.log({data});

//   return (
//     // The AdminBlock component provides an API for setting the title of the Block extension wrapper.
//     <AdminBlock title="My Block Extension">
//       <BlockStack>
//         <Text fontWeight="bold">{i18n.translate('welcome', {target: TARGET})}</Text>
//       </BlockStack>
//     </AdminBlock>
//   );
// }

import {
  reactExtension,
  useApi,
  AdminBlock,
  BlockStack,
  Text,
  InlineStack,
  Link,
} from '@shopify/ui-extensions-react/admin';
import { useEffect, useState } from 'react';

const TARGET = 'admin.order-details.block.render';

export default reactExtension(TARGET, () => <App />);

function App() {
  const { data } = useApi(TARGET);
  const orderGID = data?.selected?.[0]?.id;
  const orderId = orderGID?.split('/')?.pop();

  const [prescriberLogs, setPrescriberLogs] = useState([]);
  const [checkerLogs, setCheckerLogs] = useState([]);
  const [pdfLink, setPdfLink] = useState(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    if (!orderId) return;

    const fetchLogs = async () => {
      setLoading(true);
      try {
        const response = await fetch(
          'https://rightangled.24livehost.com/api/prescriber/audit-logs/order',
          {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ order_id: orderId }),
          }
        );

        const result = await response.json();
        setPrescriberLogs(result?.prescriber_logs || []);
        setCheckerLogs(result?.checker_logs || []);
        setPdfLink(result?.prescribed_pdf || null);
      } catch (error) {
        console.error('❌ Error fetching logs:', error);
      } finally {
        setLoading(false);
      }
    };

    fetchLogs();
  }, [orderId]);



  const renderLogs = (logs, title, showPdf = false) => (
    <AdminBlock title={title}>
      <BlockStack
        spacing="loose"
        style={{
          maxHeight: '100px', // Adjust height as needed
          overflowY: 'auto',
          paddingRight: '8px', // Add right padding to avoid hiding scrollbar
        }}
      >
        {logs.map((log) => (
          <BlockStack key={log.id} spacing="tight">
            <Text tone="subdued">{log.details}</Text>
            <Text size="small" tone="secondary">Action: {log.action}</Text>
          </BlockStack>
        ))}

        {showPdf && pdfLink && (
          <InlineStack>
            <Text size="medium" emphasis="bold">Prescribed PDF:</Text>
            <Link to={pdfLink} target="_blank">🔗 View PDF</Link>
          </InlineStack>
        )}
      </BlockStack>
    </AdminBlock>
  );


  if (loading) {
    return (
      <AdminBlock title="Audit Logs">
        <Text>Loading logs...</Text>
      </AdminBlock>
    );
  }

  return (
    <>
      {prescriberLogs.length > 0 && renderLogs(prescriberLogs, 'Prescriber Audit Logs', true)}
      {checkerLogs.length > 0 && renderLogs(checkerLogs, 'Checker Audit Logs')}
    </>
  );
}
