
// import {
//   reactExtension,
//   useApi,
//   AdminBlock,
//   BlockStack,
//   Text,
//   InlineStack,
// } from '@shopify/ui-extensions-react/admin';
// import { useEffect, useState } from 'react';

// const TARGET = 'admin.order-details.block.render';

// export default reactExtension(TARGET, () => <App />);

// function App() {
//   const { data } = useApi(TARGET);

//   // GID and numeric order ID
//   const orderGID = data?.selected?.[0]?.id;
//   console.log('🔍 orderGID:', orderGID);

//   const orderId = orderGID?.split('/')?.pop();
//   console.log('🔍 orderId:', orderId);

//   const [logs, setLogs] = useState([]);
//   const [loading, setLoading] = useState(true);

//   useEffect(() => {
//     if (!orderId) {
//       console.warn('⛔ No orderId found. Skipping fetch.');
//       return;
//     }

//     const fetchLogs = async () => {
//       setLoading(true);
//       console.log('📡 Fetching logs for orderId:', orderId);

//       try {
//         const response = await fetch(
//           'https://d8a9-136-232-169-245.ngrok-free.app/api/prescriber/audit-logs/order',
//           {
//             method: 'POST',
//             headers: { 'Content-Type': 'application/json' },
//             body: JSON.stringify({ order_id: orderId }),
//           }
//         );

//         console.log('🌐 Response status:', response.status);

//         const result = await response.json();
//         console.log('✅ API response:', result);

//         setLogs(result);
//       } catch (error) {
//         console.error('❌ Error fetching audit logs:', error);
//         setLogs([]);
//       } finally {
//         console.log('🔁 Finished fetching logs');
//         setLoading(false);
//       }
//     };

//     fetchLogs();
//   }, [orderId]);

//   return (
//     <AdminBlock title="Prescriber Audit Logs">
//       <BlockStack spacing="loose">
//         {loading ? (
//           <Text>Loading logs...</Text>
//         ) : logs.length === 0 ? (
//           <Text>No Prescriber logs found for this orders.</Text>
//         ) : (
//           logs.map((log) => {
//             console.log('📝 Rendering log:', log);
//             return (
//               <BlockStack key={log.id} spacing="tight">
//                 <InlineStack>
//                   <Text>🕓 {new Date(log.created_at).toLocaleString()}</Text>
//                 </InlineStack>
//                 <Text tone="subdued">{log.details}</Text>
//                 <Text size="small" tone="secondary">Action: {log.action}</Text>
//               </BlockStack>
//             );
//           })
//         )}
//       </BlockStack>
//     </AdminBlock>
//   );
// }

// import {
//   reactExtension,
//   useApi,
//   AdminBlock,
//   BlockStack,
//   Text,
//   InlineStack,
//   Link,
// } from '@shopify/ui-extensions-react/admin';
// import { useEffect, useState } from 'react';

// const TARGET = 'admin.order-details.block.render';

// export default reactExtension(TARGET, () => <App />);

// function App() {
//   const { data } = useApi(TARGET);

//   const orderGID = data?.selected?.[0]?.id;
//   const orderId = orderGID?.split('/')?.pop();

//   const [logs, setLogs] = useState([]);
//   const [pdfLink, setPdfLink] = useState(null);
//   const [loading, setLoading] = useState(true);

//   useEffect(() => {
//     if (!orderId) {
//       console.warn('⛔ No orderId found. Skipping fetch.');
//       return;
//     }

//     const fetchLogs = async () => {
//       setLoading(true);
//       try {
//         const response = await fetch(
//           'https://d8a9-136-232-169-245.ngrok-free.app/api/prescriber/audit-logs/order',
//           {
//             method: 'POST',
//             headers: { 'Content-Type': 'application/json' },
//             body: JSON.stringify({ order_id: orderId }),
//           }
//         );

//         const result = await response.json();
//         setLogs(result?.logs || []);
//         setPdfLink(result?.prescribed_pdf || null);
//       } catch (error) {
//         console.error('❌ Error fetching audit logs:', error);
//         setLogs([]);
//       } finally {
//         setLoading(false);
//       }
//     };

//     fetchLogs();
//   }, [orderId]);

//   return (
//     <AdminBlock title="Prescriber Audit Logs">
//       <BlockStack spacing="loose">
//         {loading ? (
//           <Text>Loading logs...</Text>
//         ) : logs.length === 0 ? (
//           <Text>No Prescriber logs found for this order.</Text>
//         ) : (
//           logs.map((log) => (
//             <BlockStack key={log.id} spacing="tight">
//               <InlineStack>
//                 <Text>🕓 {new Date(log.created_at).toLocaleString()}</Text>
//               </InlineStack>
//               <Text tone="subdued">{log.details}</Text>
//               {/* <Text size="small" tone="secondary">Action: {log.action}</Text> */}
//             </BlockStack>
//           ))
//         )}

//         {pdfLink && (
//           <InlineStack>
//             <Text size="medium" emphasis="bold">Prescribed PDF:</Text>
//             <Link to={pdfLink} target="_blank">
//               🔗 View PDF
//             </Link>
//           </InlineStack>
//         )}
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
